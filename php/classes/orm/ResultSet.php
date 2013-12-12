<?hh

namespace beatbox\orm;

use HH\Traversable;

class ResultSet implements \Iterable {
	use \LazyIterable;

	private \Vector<Result> $results;
	private ?Connection $conn = null;

	public static function lazy_result_set(Connection $conn) : ResultSet {
		$set = new ResultSet();
		$set->resultsFrom($conn);
		return $set;
	}

	public function __construct(Traversable<Result> $results = \Vector {}) {
		if (is_array($results)) {
			$this->results = \Vector::fromArray($results);
		} else if ($results instanceof \Vector) {
			$this->results = clone $results;
		} else {
			$this->results = \Vector::fromItems($results);
		}
	}

	/**
	 * Gets the first result in the set. If there are no results in the
	 * set, then it will return null.
	 */
	public function getFirst() : Result {
		if ($this->results->count() > 0) {
			return $this->results->at(0);
		} else if ($this->conn) {
			$result = pg_get_result($this->conn->_getRawConn());
			if ($result) {
				$result = Result::from_raw_result($result);
				$this->results->add($result);
				return $result;
			}
		}
		return null;
	}

	/**
	 * Returns whether or not this result set is "lazy". Whether the actual
	 * results have been retrieved yet or not
	 */
	public function isLazy() : \bool {
		return $this->conn != null;
	}

	/**
	 * Forces the result set to load the entire set of results
	 */
	public function loadRest() : \void {
		if ($this->conn) {
			// iterating over the result set causes them all to be loaded
			foreach ($this as $res) { }
			// Null out the connection, since we don't have any use for it
			// anymore
			$this->conn = null;
		}
	}

	public function getIterator() : ResultSetIterator {
		return new ResultSetIterator($this->results, $this->conn);
	}

	private function resultsFrom(Connection $conn) : \void {
		$this->conn = $conn;
	}
}

class ResultSetIterator implements \Iterator {
	private \Vector<Result> $results = null;
	private Connection $conn = null;

	private \int $cur_idx = 0;

	public function __construct(\Vector<Result> $results, Connection $conn) {
		$this->results = $results;
		$this->conn = $conn;
		// Preload first result
		$this->next();
		// Rewind again
		$this->rewind();
	}

	public function current() : Result {
		if ($this->cur_idx == $this->results->count()) {
			return null;
		} else {
			return $this->results->at($this->cur_idx);
		}
	}

	public function key() : int {
		return $this->cur_idx;
	}

	public function next() : \void {
		$this->cur_idx++;
		if ($this->cur_idx >= $this->results->count() && $this->conn) {
			// We seem to have run out of results, try to get the next one
			// we do it here, because otherwise valid could return true, and
			// then current return false, which should fail.
			$res = pg_get_result($this->conn->_getRawConn());
			if ($res === false) {
				// End of the set, we don't need the connection anymore
				$this->conn = null;
			} else {
				$this->results->add(Result::from_raw_result($res));
			}
		}
	}

	public function rewind() : \void {
		$this->cur_idx = 0;
	}

	public function valid() : \bool {
		return $this->cur_idx < $this->results->count();
	}
}
