<?hh

namespace beatbox\orm;

use HH\Traversable;

abstract class DataTable {
	/**
	 * Updates the fields in the object from the data in the row.
	 * This assumes that the given row is from the database and
	 * therefore resets and marked changes for this object.
	 */
	abstract protected function updateFromRow(\mixed $row) : \void;

	/**
	 * Get a map of updated columns for this object, the keys are the
	 * column names, the values are the updated values
	 *
	 * @return Map
	 */
	abstract protected function getUpdatedColumns() : \Map;

	/**
	 * Returns a map of column names to their current values
	 */
	abstract public function toMap(): \Map;

	/**
	 * Returns the fields in as a ROW constructor literal
	 */
	abstract public function toRow(): \string;

	/**
	 * Returns whether or not this object is "new", i.e. will
	 * be inserted instead of updated
	 */
	abstract public function isNew(): \bool;

	/**
	 * Returns the table name for this object (normally the
	 * class name)
	 */
	abstract public static function getTableName(): \string;

	/**
	 * Returns a set of column names for this object
	 */
	abstract static function getColumnNames() : \Set<\string>;

	/**
	 * Returns a set of column names that are primary keys
	 */
	abstract static function getPrimaryKeys() : \Set<\string>;

	/**
	 * ORM-getter for this class (uses LSB)
	 *
	 * @return ORM
	 */
	public static function get() : ORM {
		return new ORM(get_called_class());
	}

	/**
	 * Gets the the object using the primary key given.
	 *
	 * Because Postgres supports multi-column primary keys,
	 * this expects either a single value, or map (array or
	 * Map) of column-name => value for the "id".
	 *
	 * If the requested class only has 1 primary key, then
	 * either can be specified, if it has > 1 primary key, then
	 * all values for the columns in the key must be provided.
	 *
	 * Returns null if there is no matching object in the database
	 */
	public static function get_by_pk(\mixed $id) : DataTable {
		if(!$id) {
			return null;
		}
		$pks = static::getPrimaryKeys();
		if ($pks->count() == 1 && !(is_array($id) || $id instanceof ConstMapAccess)) {
			$id = [array_values($pks->toArray())[0] => $id];
		} else {
			if (!(is_array($id) || $id instanceof ConstMapAccess)) {
				throw new \InvalidArgumentException("Object has multi-column primary key, `get_by_id` expects an array or map");
			}
		}
		$orm = static::get();
		// Add each key as an '=' filter
		foreach ($pks as $col) {
			if (!isset($id[$col]))
				throw new \InvalidArgumentException("Missing entry for column \"$col\"");
			$orm = $orm->filter($col, $id[$col]);
		}

		return $orm->getNth(0);
	}

	/**
	 * Wrapper method for get_by_pk.
	 */
	public static function get_by_id(\mixed $id) : DataTable {
		return static::get_by_pk($id);
	}

	/**
	 * Convert the row into an object of this type
	 *
	 * @return DataTable
	 */
	public static function load(array $row) : DataTable {
		$c = new static($row);
		return $c;
	}

	/**
	 * Convert the rows into objects of this type
	 *
	 * @return array
	 */
	public static function loadMany(Traversable $rows) : \Vector {
		$v = \Vector {};
		foreach ($rows as $row) {
			$v->add(static::load($row));
		}
		return $v;
	}

	private \bool $deleted = false;

	/**
	 * Write this object to the database.
	 *
	 * $force forces a write of all columns, whether they've
	 * been updated or not;
	 *
	 * If this object is not new, and any primary key columns have
	 * been updated, the results are undefined as the code uses the
	 * current values of the primary key columns for the WHERE clause
	 * in the UPDATE query.
	 */
	public function write(\bool $force=false) : \bool {
		return $this->writeWithConn(Connection::get(), $force);
	}

	/**
	 * Write this object to the database using the given connection,
	 * $force has the same meaning as for write
	 */
	public function writeWithConn(Connection $conn, \bool $force=false) : \bool {
		if ($conn == null)
			throw new \InvalidArgumentException("Connection object is null");
		if ($this->deleted)
			throw new \DeletedObjectException('write', get_called_class());

		if ($force) {
			$values = $this->toMap();
		} else {
			$values = $this->getUpdatedColumns();
		}

		if ($values->count() == 0 && !$this->isNew()) return false;

		$table = $conn->escapeIdentifier(static::getTableName());

		if ($this->isNew()) {

			$columns = $values->keys()->map(function ($col) use ($conn) {
				return $conn->escapeIdentifier($col);
			});
			$values = $values->values()->map(function ($val) use ($conn) {
				return $conn->escapeValue($val);
			});

			foreach(($this->getPrimaryKeys()->count() ? $this->getPrimaryKeys() : ['ID']) as $k) {
				$func = "get$k";
				if($this->$func() === null) {
					$columns[] = $conn->escapeIdentifier($k);
					$values[] = 'DEFAULT';
				}
			}

			$query = "INSERT INTO $table (".pr_join(',', $columns).') '.
				'VALUES ('.pr_join(',',$values).') '.
				'RETURNING *;';
		} else {
			$allVals = $this->toMap();
			$primaryKeys = static::getPrimaryKeys();

			$pairs = $values->kvzip()->map(function (\Pair $pair) use ($conn) {
				$col = $conn->escapeIdentifier($pair[0]);
				$val = $conn->escapeValue($pair[1]);
				return "$col = $val";
			});
			$pks = $primaryKeys->map(function ($col) use ($conn, $allVals) {
				$val = $allVals[$col];
				$col = $conn->escapeIdentifier($col);
				$val = $conn->escapeValue($val);
				return "$col=$val";
			});

			$query = "UPDATE $table SET ".pr_join(', ', $pairs)." WHERE ".
				pr_join(' AND ', $pks).'RETURNING *;';
		}

		$result = $conn->queryBlock($query);
		assert($result instanceof QueryResult && "Object write should always return rows");
		assert($result->numRows() == 1 && "Object write should only return one row");

		$row = $result->nthRow(0);

		$this->updateFromRow($row);

		return true;
	}

	/**
	 * Deletes this object.
	 *
	 * The object shouldn't be used after deletion and will cause exceptions
	 * to be thrown if this happens.
	 */
	public function delete() : \void {
		if ($this->deleted)
			throw new \DeletedObjectException('delete', get_called_class());
		$this->deleteWithConn(Connection::get());
	}

	public function deleteWithConn(Connection $conn) : \void {
		if ($this->deleted)
			throw new \DeletedObjectException('delete', get_called_class());
		$this->deleted = true;

		// This object was never written, save us some work by not even
		// trying to delete
		if ($this->isNew()) return;

		$table = $conn->escapeIdentifier(static::getTableName());

		$allVals = $this->toMap();
		$primaryKeys = static::getPrimaryKeys();

		$pks = $primaryKeys->map(function ($col) use ($conn, $allVals) {
			$val = $allVals[$col];
			$col = $conn->escapeIdentifier($col);
			$val = $conn->escapeValue($val);
			return "$col=$val";
		});

		$query = "DELETE FROM $table WHERE ".pr_join(' AND ', $pks).';';

		$result = $conn->queryBlock($query);
		assert($result instanceof ModifyResult);
		assert($result->isDelete());
		/*
		 * Asserting that only one row was deleted should help to find bugs,
		 * and since assertions are ignored in production, if it happens in
		 * then it won't crash a page that could probably recover or ignore
		 * this postcondition.
		 */
		assert($result->numRows() == 1);
	}

}
