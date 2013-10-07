<?php

namespace beatbox\orm;

class ResultSet implements \Iterable {
	use \IterableTrait;

	private $results = null;
	private $conn = null;

	public static function lazy_result_set(Connection $conn) : ResultSet {
		$set = new ResultSet();
		$set->resultsFrom($conn);
		return $set;
	}

	public function __construct(\Traversable<Result> $results = \Vector {}) {
		if (is_array($results)) {
			$this->results = \Vector::fromArray($results);
		} else if ($results instanceof \Vector) {
			$this->results = clone $results;
		} else {
			$this->results = \Vector::fromItems($results);
		}
	}

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

	public function isLazy() : bool {
		return $this->conn != null;
	}

	public function loadRest() {
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

	private function resultsFrom(Connection $conn) {
		$this->conn = $conn;
	}
}

class ResultSetIterator implements \Iterator {
	private $results = null;
	private $conn = null;

	private $cur_idx = 0;

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

	public function next() {
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

	public function rewind() {
		$this->cur_idx = 0;
	}

	public function valid() {
		return $this->cur_idx < $this->results->count();
	}
}
