<?hh

namespace beatbox\orm;

use Indexish;

abstract class DataTable {
	abstract public function __construct(?Indexish<string,?string> $row = null);

	/**
	 * Updates the fields in the object from the data in the row.
	 * This assumes that the given row is from the database and
	 * therefore resets and marked changes for this object.
	 */
	abstract protected function updateFromRow(Indexish<string,?string> $row) : void;

	/**
	 * Get a map of updated columns for this object, the keys are the
	 * column names, the values are the updated values
	 *
	 * @return Map
	 */
	abstract protected function getUpdatedColumns() : Map<string,mixed>;

	/**
	 * Get a map of the original values for this object
	 */
	abstract protected function originalValues(): ImmMap<string,mixed>;

	/**
	 * Returns a map of column names to their current values
	 */
	abstract public function toMap(): Map<string,mixed>;

	/**
	 * Returns the fields in as a ROW constructor literal
	 */
	abstract public function toRow(): string;

	/**
	 * Returns whether or not this object is "new", i.e. will
	 * be inserted instead of updated
	 */
	abstract public function isNew(): bool;

	/**
	 * Returns the table name for this object (normally the
	 * class name)
	 */
	abstract public static function getTableName(): string;

	/**
	 * Returns a set of column names for this object
	 */
	abstract static function getColumnNames() : \ConstSet<string>;

	/**
	 * Returns a set of column names that are primary keys
	 */
	abstract static function getPrimaryKeys() : \ConstSet<string>;

	/**
	 * Creates a copy of this object, marking all the fields as changed
	 */
	abstract public function copy(): this;

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
	public static async function get_by_pk(mixed $id) : Awaitable<?this> {
		if(!$id) {
			return null;
		}
		$pks = static::getPrimaryKeys();
		if ($pks->count() == 1 && !($id instanceof Indexish)) {
			$id = [array_values($pks->toArray())[0] => $id];
		} else {
			if (!($id instanceof Indexish)) {
				throw new \InvalidArgumentException(
					"Object has multi-column primary key, `get_by_id` expects an array or map"
				);
			}
		}
		invariant($id instanceof Indexish, '$id should be indexable');
		$orm = static::get();
		// Add each key as an '=' filter
		foreach ($pks as $col) {
			if (!isset($id[$col]))
				throw new \InvalidArgumentException("Missing entry for column \"$col\"");
			$orm = $orm->filter($col, $id[$col]);
		}

		return await $orm->getNth(0);
	}

	/**
	 * Wrapper method for get_by_pk.
	 */
	public static function get_by_id(mixed $id) : Awaitable<?this> {
		return static::get_by_pk($id);
	}

	/**
	 * Convert the row into an object of this type
	 *
	 * @return DataTable
	 */
	public static function load(Indexish<string,string> $row) : this {
		$c = new static($row);
		return $c;
	}

	/**
	 * Convert the rows into objects of this type
	 *
	 * @return array
	 */
	public static function loadMany(Traversable<Indexish<string,string>> $rows) : Vector<DataTable> {
		$v = Vector {};
		foreach ($rows as $row) {
			$v->add(static::load($row));
		}
		return $v;
	}

	private bool $deleted = false;

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
	public async function write(bool $force=false) : Awaitable<bool> {
		return await $this->writeWithConn(Connection::get(), $force);
	}

	/**
	 * Write this object to the database using the given connection,
	 * $force has the same meaning as for write
	 */
	public async function writeWithConn(Connection $conn, bool $force=false) : Awaitable<bool> {
		if ($conn == null)
			throw new \InvalidArgumentException("Connection object is null");
		if ($this->deleted)
			throw new DeletedObjectException('write', get_called_class());

		$query = $this->getWriteQuery($conn, $force);
		if (!$query) return false;

		// We use queueQuery here because inserting a single row is fairly fast, and
		// this allows code writing many objects at once to just do a gena on all of
		// them at the end instead of opening multiple connections.
		// This also makes it faster when running inside a transaction, as transactions
		// cause queries to run immediately, instead of asynchronously.
		$result = await $conn->queueQuery($query);
		assert($result instanceof QueryResult && "Object write should always return rows");
		assert($result->numRows() == 1 || (var_dump($query) && false));

		$row = $result->nthRow(0);

		$this->updateFromRow($row);

		return true;
	}

	private function getWriteQuery(Connection $conn, bool $force) : ?string {
		if ($force) {
			$values = $this->toMap();
		} else {
			$values = $this->getUpdatedColumns();
		}

		if ($values->count() == 0 && !$this->isNew()) return null;

		$table = $conn->escapeIdentifier(static::getTableName());

		if ($this->isNew()) {

			$columns  = $values->keys()->map($col ==> $conn->escapeIdentifier($col));
			$obj_vals = $values->values()->map($val ==> $conn->escapeValue($val));

			$primary_keys = static::getPrimaryKeys();
			foreach($primary_keys as $k) {
				if($values->get($k) == null) {
					$columns[] = $conn->escapeIdentifier($k);
					$obj_vals[] = 'DEFAULT';
				}
			}

			return "INSERT INTO $table (".bb_join(',', $columns).') '.
				'VALUES ('.bb_join(',',$obj_vals).') '.
				'RETURNING *;';
		} else {
			$allVals = $this->toMap();
			$origVals = $this->originalValues();
			$primaryKeys = static::getPrimaryKeys();

			$pairs = $values->items()->map(function (Pair<string,mixed> $pair) use ($conn) {
				$col = $conn->escapeIdentifier($pair[0]);
				$val = $conn->escapeValue($pair[1]);
				return "$col = $val";
			});
			$pks = $primaryKeys->map(function ($col) use ($conn, $origVals) {
				$val = $origVals[$col];
				$col = $conn->escapeIdentifier($col);
				$val = $conn->escapeValue($val);
				return "$col=$val";
			});

			return "UPDATE $table SET ".bb_join(', ', $pairs)." WHERE ".
				bb_join(' AND ', $pks).'RETURNING *;';
		}
	}

	/**
	 * Deletes DataTable object.
	 *
	 * The object shouldn't be used after deletion and will cause exceptions
	 * to be thrown if this happens.
	 */
	public async function delete() : Awaitable<void> {
		if ($this->deleted)
			throw new DeletedObjectException('delete', get_called_class());
		return await $this->deleteWithConn(Connection::get());
	}

	public async function deleteWithConn(Connection $conn) : Awaitable<void> {
		if ($this->deleted)
			throw new DeletedObjectException('delete', get_called_class());
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

		$query = "DELETE FROM $table WHERE ".bb_join(' AND ', $pks).';';

		$result = await $conn->query($query);
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
