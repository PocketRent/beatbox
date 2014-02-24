<?hh

namespace beatbox\orm;

use Awaitable;

class ORM<T> implements \IteratorAggregate<T>, \Countable {
	protected \string $data_class;
	protected \string $table;
	protected Connection $conn;

	private Set<\string> $valid_fields;

	protected Vector $conds = Vector {};
	protected Vector $sorts = Vector {};
	protected Vector $joins = Vector {};
	private \int $limit = -1;
	private \int $offset = -1;
	private ?\string $from = null;

	private ?Result $result = null;
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
	public function setDataClass<Tn>(\string $data_class) : ORM<Tn> {
		// UNSAFE
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
	public function setFrom(\string $from) : ORM<T> {
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
	public function filter(\string $field, \mixed $value, \string $comp = '=') : ORM<T> {
		$this->validateField($field);
		$field = $this->conn->escapeIdentifier($field);
		if(is_null($value)) {
			if($comp == '=') {
				$comp = ' IS ';
			} else if($comp == '!=') {
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
	public function where(\string $clause) : ORM<T> {
		$new = clone $this;
		$new->conds->add(trim($clause));
		return $new;
	}

	/**
	 * Add an ORDER BY clause for the given field in the given direction.
	 */
	public function sortBy(\string $field, \string $direction = 'ASC') : ORM<T> {
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
	public function sort(\string $clause) : ORM<T> {
		$new = clone $this;
		$new->sorts->add(trim($clause));
		return $new;
	}

	/**
	 * Add a JOIN clause
	 *
	 * This is the entire join clause, including the 'JOIN' keyword.
	 */
	public function join(\string $clause) : ORM<T> {
		$new = clone $this;
		$new->joins->add(trim($clause));
		return $new;
	}

	/**
	 * Add a LIMIT value.
	 *
	 * If $value is less than 0, then no LIMIT is added.
	 */
	public function limit(?\int $value, ?\int $offset = null) : ORM<T> {
		if ($value === null) $value = -1;
		$new = clone $this;
		$new->limit = (int)$value;
		if($offset !== null) {
			$new->offset = (int)$offset;
		}
		return $new;
	}

	/**
	 * Add an OFFSET value.
	 *
	 * If $value is less than 0, then no OFFSET is added.
	 */
	public function offset(?\int $value) : ORM<T> {
		if ($value === null) $value = -1;
		$new = clone $this;
		$new->offset = (int)$value;
		return $new;
	}

	public function count() : int {
		$res = wait($this->countWith('*', 'C')->getNth(0));
		return (int)nullthrows($res)->at('C');
	}

	/**
	 * Helper method to create an AggregateORM and add the given
	 * count field to it.
	 *
	 * If no arguments are provided, returns the actual count
	 */
	public function countWith(\string $field = '*', ?\string $as = null) : AggregateORM {
		$agg = $this->agg();
		return $agg->countWith($field, $as);
	}

	/**
	 * Helper method to create an AggregateORM and add the given
	 * max field to it.
	 */
	public function max(\string $field, ?\string $as = null) : AggregateORM {
		$agg = $this->agg();
		return $agg->max($field, $as);
	}

	/**
	 * Helper method to create an AggregateORM and add the given
	 * min field to it.
	 */
	public function min(\string $field, ?\string $as = null) : AggregateORM {
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
	public function fetch() : Iterable<T> {
		$result = wait($this->getResult());
		$cls = $this->data_class;
		// Making the objects pretty much just consists of throwing
		// each row at the class' `load` static method, so there's no
		// point creating an entire new iterator for it.
		return $result->rows()->map(function ($row) use ($cls) {
			return $cls::load($row);
		});
	}

	public function getIterator() : Iterator {
		return $this->fetch()->getIterator();
	}

	public function getQueryString() : \string {
		$conn = $this->conn;

		$table = $this->getFrom();

		$distinct = $this->joins->count() > 0;

		if($distinct) {
			$query = 'SELECT DISTINCT ';
		} else {
			$query = 'SELECT ';
		}

		$query .= $this->getFieldList()." FROM $table";
		$query .= bb_join("\n", $this->joins);

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

		if ($this->offset >= 0) {
			$query .= "\nOFFSET ".((int)$this->offset);
		}

		return $query;
	}

	protected function getWHERE() : ?\string {
		if ($this->conds->count() > 0)
			return 'WHERE ('.bb_join(') AND (', $this->conds).')';
		else
			return null;
	}

	protected function getGROUP_BY() : ?\string { return null; }

	protected function getHAVING() : ?\string { return null; }

	protected function getORDER_BY() : ?\string {
		if ($this->sorts->count() > 0)
			return "ORDER BY ".bb_join(', ', $this->sorts);
		else
			return null;
	}

	protected function getFieldList() : \string {
		$conn = $this->conn;
		$table = $this->conn->escapeIdentifier($this->table);
		$fields = $this->valid_fields->map(function ($f) use ($conn, $table) {
			return "$table.".$this->conn->escapeIdentifier($f);
		});

		return bb_join(', ', $fields);
	}

	protected function getPrimaryKeyList() : \string {
		$conn = $this->conn;
		$table = $this->conn->escapeIdentifier($this->table);
		$data_class = $this->data_class;
		$fields = $data_class::getPrimaryKeys()->map(function ($f) use ($conn, $table) {
			return "$table.".$this->conn->escapeIdentifier($f);
		});

		return bb_join(', ', $fields);
	}

	/**
	 * Gets the nth result from the results, starting at 0
	 */
	public async function getNth(\int $n) : Awaitable<?T> {
		$result = await $this->getResult();
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

	protected async function getResult() : Awaitable<QueryResult> {
		if ($this->result === null) {
			$q = $this->getQueryString();
			$this->result = await $this->conn->query($q);
		}

		invariant($this->result instanceof QueryResult, "Result should be a QueryResult");

		return $this->result;
	}

	protected function validateField(\string $field) : \void {
		if (!$this->valid_fields->contains($field)) {
			throw new InvalidFieldException($field, $this->valid_fields);
		}
	}
}

class AggregateORM extends ORM<Map<string,string>> {

	private Set<string> $extra_fields = Set {};
	private Vector<string> $group_bys = Vector {};
	private Vector<string> $having = Vector {};

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
	public function max(\string $field, ?\string $as = null) : AggregateORM {
		$this->validateField($field);

		if (!$as) {
			$as = "max_$field";
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
	 * If no alias is given, then it defaults to 'min_<FieldName>'
	 */
	public function min(\string $field, ?\string $as = null) : AggregateORM {
		$this->validateField($field);

		if (!$as) {
			$as = "min_$field";
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
	public function countWith(\string $field = '*', ?\string $as = null) : AggregateORM {
		if ($field != '*') {
			$this->validateField($field);

			if (!$as) {
				$as = "count_$field";
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
			return "GROUP BY ".bb_join(', ', $this->group_bys);
		else
			return null;
	}

	protected function getHAVING() : ?\string {
		if ($this->having->count() > 0)
			return "HAVING (".bb_join(') AND (', $this->having).')';
		else
			return null;
	}

	protected function getFieldList() : \string {
		assert($this->extra_fields->count() > 0 && "Can't select no fields!");
		return bb_join(', ', $this->extra_fields);
	}

	protected function getPrimaryKeyList() : \string {
		return '';
	}

	/**
	 * Returns an iterable over the rows, the iterable returns
	 * the rows as associative arrays, not objects.
	 */
	public function fetch() : Iterable {
		return wait($this->getResult())->rows();
	}

	/**
	 * Returns an iterable returning the objects in the
	 * results, only use if you are sure the data will be valid
	 * for the object
	 */
	public function fetchObjects<T as DataTable>() : Iterable<T> {
		$result = $this->getResult();
		$cls = $this->data_class;
		return wait($result)->rows()->map(function ($row) use ($cls) {
			return $cls::load($row);
		});
	}

	/**
	 * Returns the nth row or null
	 */
	public async function getNth(\int $n) : Awaitable<?Map<\string,\string>> {
		$result = await $this->getResult();
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
