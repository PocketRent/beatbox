<?hh

namespace beatbox\orm;

class ORM implements \IteratorAggregate, \Countable {
	private $data_class;
	protected $table;
	protected $conn;

	private $valid_fields;

	private $conds = \Vector {};
	private $sorts = \Vector {};
	private $joins = \Vector {};
	private $limit = -1;
	private $from = null;

	private $result = null;
	/**
	 * Create an ORM-instance for getting this class
	 */
	public function __construct(\string $data_class) {
		$this->data_class = $data_class;
		$this->table = $data_class::getTableName();
		$this->conn = Connection::get();
		$this->valid_fields = $data_class::getColumnNames();
	}

	/**
	 * Sets the class that will be constructed to the given name.
	 *
	 * The underlying tables must, however, be the same.
	 */
	public function setDataClass(\string $data_class) : ORM {
		$new = clone $this;
		$new->data_class = $data_class;
		$new->table = $data_class::getTableName();
		$new->valid_fields = $data_class::getColumnNames();

		assert($new->table == $this->table && "Tables should be the same");

		return $new;
	}

	/**
	 * Set a custom FROM clause
	 *
	 * The new clause should be an escaped identifier and should return rows
	 * that match the data class's.
	 */
	public function setFrom(\string $from) : ORM {
		$new = clone $this;
		$new->from = $from;

		return $new;
	}

	/**
	 * Get the escaped FROM clause
	 */
	public function getFrom() : \string {
		if($this->from) {
			return sprintf('%s AS %s', $this->from, $this->conn->escapeIdentifier($this->table));
		}
		return $this->conn->escapeIdentifier($this->table);
	}

	/**
	 * Only select objects with the field related to the value by the comparison operator
	 *
	 * The field must be a field on the object being queried for.
	 */
	public function filter(\string $field, \mixed $value, \string $comp = '=') : ORM {
		$this->validateField($field);
		$field = $this->conn->escapeIdentifier($field);
		if(is_null($value)) {
			if($comp == '=') {
				$comp = ' IS ';
			} elseif($comp == '!=') {
				$comp = ' IS NOT ';
			}
		}
		$value = $this->conn->escapeValue($value);
		$table = $this->conn->escapeIdentifier($this->table);
		return $this->where("$table.$field$comp$value");
	}

	/**
	 * Add a WHERE clause.
	 *
	 * The clause is the actual condition and is 'AND'-ed with the other conditions
	 * provided. The contents is expected to already be escaped.
	 */
	public function where(\string $clause) : ORM {
		$new = clone $this;
		$new->conds->add(trim($clause));
		return $new;
	}

	/**
	 * Add an ORDER BY clause for the given field in the given direction.
	 */
	public function sortBy(\string $field, \string $direction = 'ASC') : ORM {
		$direction = $direction == 'DESC' ? 'DESC' : 'ASC';
		$this->validateField($field);
		$field = $this->conn->escapeIdentifier($field);
		$table = $this->conn->escapeIdentifier($this->table);
		return $this->sort("$table.$field $direction");
	}

	/**
	 * Add a ORDER BY clause.
	 *
	 * This is the actual clause, so don't include the 'ORDER BY'.
	 * The clause is expected to already be escaped
	 */
	public function sort(\string $clause) : ORM {
		$new = clone $this;
		$new->sorts->add(trim($clause));
		return $new;
	}

	/**
	 * Add a JOIN clause
	 *
	 * This is the entire join clause, including the 'JOIN' keyword.
	 */
	public function join(\string $clause) : ORM {
		$new = clone $this;
		$new->joins->add(trim($clause));
		return $new;
	}

	/**
	 * Add a LIMIT value.
	 *
	 * If $value is less than 0, then no LIMIT is added.
	 */
	public function limit(\int $value) : ORM {
		if ($value === null) $value = -1;
		$new = clone $this;
		$new->limit = (int)$value;
		return $new;
	}

	/**
	 * Helper method to create an AggregateORM and add the given
	 * count field to it.
	 *
	 * If no arguments are provided, returns the actual count
	 */
	public function count(\string $field = '*', \string $as = null) : AggregateORM {
		if(func_num_args() == 0) {
			return $this->agg()->count('*', 'C')->getNth(0)['C'];
		}
		$agg = $this->agg();
		return $agg->count($field, $as);
	}

	/**
	 * Helper method to create an AggregateORM and add the given
	 * max field to it.
	 */
	public function max(\string $field, \string $as = null) : AggregateORM {
		$agg = $this->agg();
		return $agg->max($field, $as);
	}

	/**
	 * Helper method to create an AggregateORM and add the given
	 * min field to it.
	 */
	public function min(\string $field, \string $as = null) : AggregateORM {
		$agg = $this->agg();
		return $agg->min($field, $as);
	}

	/**
	 * Returns the aggregate version of this query so you
	 * can add aggregate clauses
	 */
	public function agg() : AggregateORM {
		$agg = new AggregateORM($this->data_class);

		$agg->conds = clone $this->conds;
		$agg->sorts = clone $this->sorts;
		$agg->joins = clone $this->joins;

		return $agg;
	}

	/**
	 * Returns a lazy iterator over the result set
	 */
	public function fetch() : \Iterable {
		$result = $this->getResult();
		$cls = $this->data_class;
		// Making the objects pretty much just consists of throwing
		// each row at the class' `load` static method, so there's no
		// point creating an entire new iterator for it.
		return $result->rows()->map(function ($row) use ($cls) {
			return $cls::load($row);
		});
	}

	public function getIterator() : \Iterator {
		return $this->fetch()->getIterator();
	}

	public function getQueryString() : \string {
		$conn = $this->conn;

		$table = $this->getFrom();

		$query = "SELECT DISTINCT ".$this->getFieldList()." FROM $table";
		$query .= pr_join("\n", $this->joins);

		// WHERE
		$where = $this->getWHERE();
		if ($where)
			$query .= "\n$where";

		// GROUP BY
		$group_by = $this->getGROUP_BY();
		if ($group_by)
			$query .= "\n$group_by";

		// HAVING
		$having = $this->getHAVING();
		if ($having)
			$query .= "\n$having";

		// ORDER BY
		$order_by = $this->getORDER_BY();
		if ($order_by)
			$query .= "\n$order_by";

		if ($this->limit >= 0) {
			$query .= "\nLIMIT ".((int)$this->limit);
		}

		return $query;
	}

	protected function getWHERE() : ?\string {
		if ($this->conds->count() > 0)
			return 'WHERE ('.pr_join(') AND (', $this->conds).')';
		else
			return false;
	}

	protected function getGROUP_BY() : ?\string { return false; }

	protected function getHAVING() : ?\string { return false; }

	protected function getORDER_BY() : ?\string {
		if ($this->sorts->count() > 0)
			return "ORDER BY ".pr_join(', ', $this->sorts);
		else
			return false;
	}

	protected function getFieldList() : \string {
		$conn = $this->conn;
		$table = $this->conn->escapeIdentifier($this->table);
		$fields = $this->valid_fields->map(function ($f) use ($conn, $table) {
			return "$table.".$this->conn->escapeIdentifier($f);
		});

		return pr_join(', ', $fields);
	}

	/**
	 * Gets the nth result from the results, starting at 0
	 */
	public function getNth(\int $n) : DataTable {
		$result = $this->getResult();
		if ($n < $result->numRows()) {
			$cls = $this->data_class;
			$row = $result->nthRow($n);
			return $cls::load($row);
		} else {
			return null;
		}
	}

	public function __clone() : \void {
		$this->result = null;
		$this->conds = clone $this->conds;
		$this->sorts = clone $this->sorts;
		$this->joins = clone $this->joins;
	}

	protected function getResult() : Result {
		if ($this->result === null) {
			$q = $this->getQueryString();
			$this->result = $this->conn->queryBlock($q);
			assert($this->result instanceof QueryResult);
		}

		return $this->result;
	}

	protected function validateField(\string $field) : \void {
		if (!$this->valid_fields->contains($field)) {
			throw new InvalidFieldException($field, $this->valid_fields);
		}
	}
}

class AggregateORM extends ORM {

	private $extra_fields = \Set {};
	private $group_bys = \Vector {};
	private $having = \Vector {};

	/**
	 * Adds a field to select from the database, use $force to
	 * disable field validation. If $force is true, then $field
	 * should already be escaped.
	 */
	public function addField(\string $field, \bool $force=false) : AggregateORM {
		if (!$force) {
			$this->validateField($field);
			$table = $this->conn->escapeIdentifier($this->table);
			$field = $this->conn->escapeIdentifier($field);
			$field = "$table.$field";
		}

		$new = clone $this;
		$new->extra_fields->add($field);
		return $new;
	}

	/**
	 * Adds a MAX field over the given field with the provided alias
	 *
	 * If no alias is given, then it defaults to 'max_<FieldName>'
	 */
	public function max(\string $field, \string $as = null) : AggregateORM {
		$this->validateField($field);

		if (!$as) {
			$as = 'max_$field';
		}

		$table = $this->conn->escapeIdentifier($this->table);
		$field = $this->conn->escapeIdentifier($field);
		$field = "$table.$field";

		$as = $this->conn->escapeIdentifier($as);

		$clause = "MAX($field) as $as";

		$new = clone $this;
		$new->extra_fields->add($clause);
		return $new;
	}

	/**
	 * Adds a MAX field over the given field with the provided alias
	 *
	 * If no alias is given, then it defaults to 'max_<FieldName>'
	 */
	public function min(\string $field, \string $as = null) : AggregateORM {
		$this->validateField($field);

		if (!$as) {
			$as = 'count_$field';
		}

		$table = $this->conn->escapeIdentifier($this->table);
		$field = $this->conn->escapeIdentifier($field);
		$field = "$table.$field";

		$as = $this->conn->escapeIdentifier($as);

		$clause = "MIN($field) as $as";

		$new = clone $this;
		$new->extra_fields->add($clause);
		return $new;
	}

	/**
	 * Adds a COUNT field over the given field with the provided alias
	 *
	 * If no field is given, then it defaults to '*'. If no alias is
	 * given, then it defaults to 'count_<FieldName>' unless the field
	 * is '*', in which case it is 'count_<NumExtraFields>'
	 */
	public function count(\string $field = '*', \string $as = null) : AggregateORM {
		if ($field != '*') {
			$this->validateField($field);

			if (!$as) {
				$as = 'count_$field';
			}

			$table = $this->conn->escapeIdentifier($this->table);
			$field = $this->conn->escapeIdentifier($field);
			$field = "$table.$field";
		} else {
			if (!$as) {
				$as = 'count_'.$this->extra_fields->count();
			}
		}

		$as = $this->conn->escapeIdentifier($as);

		$clause = "COUNT($field) as $as";

		$new = clone $this;
		$new->extra_fields->add($clause);
		return $new;
	}

	/**
	 * Adds a GROUP BY clause for the given field
	 */
	public function groupByField(\string $field) : AggregateORM {
		$new = $this->addField($field);

		$table = $this->conn->escapeIdentifier($this->table);
		$field = $this->conn->escapeIdentifier($field);
		$field = "$table.$field";

		return $new->groupBy("$field");
	}

	/**
	 * Adds a GROUP BY clause. Expects the given string to already
	 * be escaped.
	 */
	public function groupBy(\string $clause) : AggregateORM {
		$new = clone $this;
		$new->group_bys->add($clause);
		return $new;
	}

	/**
	 * Adds a HAVING condition. Expects the given string to already
	 * be escaped.
	 */
	public function having(\string $clause) : AggregateORM {
		$new = clone $this;
		$new->having->add($clause);
		return $new;
	}

	protected function getGROUP_BY() : ?\string {
		if ($this->group_bys->count() > 0)
			return "GROUP BY ".pr_join(', ', $this->group_bys);
		else
			return false;
	}

	protected function getHAVING() : ?\string {
		if ($this->having->count() > 0)
			return "HAVING (".pr_join(') AND (', $this->having).')';
		else
			return false;
	}

	protected function getFieldList() : \string {
		assert($this->extra_fields->count() > 0 && "Can't select no fields!");
		return pr_join(', ', $this->extra_fields);
	}

	/**
	 * Returns an iterable over the rows, the iterable returns
	 * the rows as associative arrays, not objects.
	 */
	public function fetch() : \Iterable {
		return $this->getResult()->rows();
	}

	/**
	 * Returns an iterable returning the objects in the
	 * results, only use if you are sure the data will be valid
	 * for the object
	 */
	public function fetchObjects() : \Iterable {
		$result = $this->getResult();
		$cls = $this->data_class;
		return $result->rows()->map(function ($row) use ($cls) {
			return $cls::load($row);
		});
	}

	/**
	 * Returns the nth row or null
	 */
	public function getNth(\int $n) : ?array {
		$result = $this->getResult();
		if ($n < $result->numRows()) {
			return $result->nthRow($n);
		} else {
			return null;
		}
	}

	public function __clone() : \void {
		parent::__clone();
		$this->extra_fields = clone $this->extra_fields;
		$this->group_bys = clone $this->group_bys;
	}
}
