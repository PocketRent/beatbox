<?hh

namespace beatbox\orm;

abstract class Result {
	protected \resource $result;

	private ?\string $tag = null;
	private \int $oid = 0;
	protected \int $count = -1;

	public static function from_raw_result(\resource $res) : Result {
		// Log the command tag, this contains enough information for simple
		// analysis.
		send_event("db::result", pg_result_status($res, PGSQL_STATUS_STRING));
		$status = pg_result_status($res);
		if ($status == PGSQL_COMMAND_OK) {
			// Success, but no data (INSERT, UPDATE, etc.)
			return new ModifyResult($res);
		} else if ($status == PGSQL_TUPLES_OK) {
			// Success and has rows, i.e. SELECT, SHOW
			return new QueryResult($res);
		} else {
			throw new ResultException($res);
		}
	}

	/*
	 * Predicate Methods
	 */

	public function isSelect() : \bool {
		return $this->getTag() == 'SELECT';
	}

	public function isInsert() : \bool {
		return $this->getTag() == 'INSERT';
	}

	public function isUpdate() : \bool {
		return $this->getTag() == 'UPDATE';
	}

	public function isDelete() : \bool {
		return $this->getTag() == 'DELETE';
	}

	public function getTag() : \string {
		$this->parseTag();
		return $this->tag;
	}

	private function parseTag() : \void {
		if ($this->tag == null) {
			$cmd_tag = pg_result_status($this->result, PGSQL_STATUS_STRING);
			$parts = explode(' ', $cmd_tag);
			$this->tag = trim($parts[0]);
			if (count($parts) == 2) {
				$this->count = (int)$parts[1];
			} else if (count($parts) == 3) {
				$this->oid = (int)$parts[1];
				$this->count = (int)$parts[2];
			}
		}
	}

	public function __construct(\resource $result) {
		$this->result = $result;
	}

	/**
	 * Returns the number of rows associated with this query,
	 * either the number returned, or the number affected
	 */
	abstract public function numRows() : \int;
}

class ModifyResult extends Result {
	private \int $num_rows = -1;

	public function numRows() : \int {
		$this->getTag();
		return $this->count;
	}

}

class QueryResult extends Result implements \IteratorAggregate {

	private \int $num_rows = -1;

	private \Vector $rows = \Vector {};

	public function __construct(\resource $result) {
		parent::__construct($result);

		$this->rows->reserve($this->numRows());
	}

	public function numRows() : \int {
		if ($this->num_rows == -1) {
			$this->num_rows = pg_num_rows($this->result);
		}
		return $this->num_rows;
	}

	/**
	 * Gets the nth row in the set, indexed from 0.
	 *
	 * Will throw an exception if the given position is out of bounds
	 */
	public function nthRow(\int $pos) : array {
		if ($pos >= 0 && $pos < $this->numRows()) {
			if ($pos >= $this->rows->count()) {
				$iter = $this->getIterator();
				for ($i=0; $i <= $pos; $i++) {
					assert($iter->valid() && "Iterator should always be valid");
					$iter->current();
					$iter->next();
				}
			}
			return $this->rows->at($pos);
		} else {
			throw new \OutOfBoundsException("Position $pos is out of bounds,".
				" must be between 0 and ".$this->numRows());
		}
	}

	/**
	 * Returns a lazy iterable over the rows in the result set
	 */
	public function rows() : ResultIterable {
		return new ResultIterable($this);
	}

	// Implement Iterable
	public function getIterator() : ResultIterator {
		return new ResultIterator($this->result, $this->rows, $this->num_rows);
	}
}

class ResultIterable implements \Iterable {
	use \LazyIterable;

	private QueryResult $result;

	public function __construct(QueryResult $result) {
		$this->result = $result;
	}

	public function getIterator() : ResultIterator {
		return $this->result->getIterator();
	}
}

class ResultIterator implements \Iterator {
	private \resource $result;
	private \Vector $rows;
	private \int $num_rows;

	private \int $cur_idx = 0;

	public function __construct(\resource $result, \Vector $rows, \int $num_rows) {
		$this->result = $result;
		$this->rows = $rows;
		$this->num_rows = $num_rows;
	}

	public function current() : array {
		if ($this->cur_idx == $this->num_rows) {
			// We're out of rows
			return null;
		}
		if ($this->cur_idx == $this->rows->count()) {
			// We've run out of rows from the given object, fetch them
			$row = pg_fetch_assoc($this->result);
			if (!$row) // We shouldn't ever get NULL, so false is an error
				throw new ResultException($this->result, "Error getting next row");
			$this->rows->add($row);
		}
		return $this->rows->at($this->cur_idx);
	}

	public function key() : \int {
		return $this->cur_idx;
	}

	public function next() : \void {
		$this->cur_idx++;
	}

	public function rewind() : \void {
		$this->cur_idx = 0;
	}

	public function valid() : \bool {
		return $this->cur_idx < $this->num_rows;
	}
}
