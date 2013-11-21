<?hh

namespace beatbox\orm;

use HH\Traversable;

/**
 * This class represents the Postgres connection used for interacting with the
 * database. It is intended for use as a singleton, with the constructor
 * filling in the blanks with defined constants.
 *
 *   DATABASE_HOST
 *   DATABASE_USER
 *   DATABASE_PASS
 *   DATABASE_NAME
 *
 * It is recommended to supply these values and use Connection::get to get an
 * instance of Connection.
 */
final class Connection {

	private static self $connection = null;

	private resource $pg_conn = null;

	/**
	 * Returns the singleton instance of Connection, creating it if necessary.
	 */
	public static function get() : Connection {
		if (self::$connection === null) {
			// The constructor will set self::$connection
			return new static;
		}
		return self::$connection;
	}

	/**
	 * Constructs a new connection and sets itself as the single instance.
	 *
	 * $params is a map from parameter name to value. The parameters host, user,
	 *    password and dbname will be automatically be filled by defined
	 *    constants
	 */
	public function __construct(array $params = null) {
		if (!$params) {
			$params = [];
		}

		// Fill in missing values from the configuration
		if (!isset($params['host']) && DATABASE_HOST) {
			$params['host'] = DATABASE_HOST;
		}
		if (!isset($params['user']) && DATABASE_USER) {
			$params['user'] = DATABASE_USER;
		}
		if (!isset($params['password']) && DATABASE_USER) {
			$params['password'] = DATABASE_PASS;
		}
		if (!isset($params['dbname']) && DATABASE_USER) {
			$params['dbname'] = DATABASE_NAME;
		}
		if (!isset($params['application_name']) && defined('APP_NAME')) {
			$params['application_name'] = APP_NAME;
		}

		$str = "";
		foreach ($params as $name => $value) {
			$str .= "$name='".addslashes($value)."' ";
		}

		$conn = @pg_connect($str);
		unset($params['password']);

		if (!$conn) {
			throw new DatabaseException("Failed to connect to database");
		}

		$this->pg_conn = $conn;

		if (pg_connection_status($conn) != PGSQL_CONNECTION_OK) {
			throw new ConnectionException($this, "Failed to connect to database");
		}

		send_event("db::connect", $params);

		// Set some connection options, the timezone is set to the local default one
		// DateStyle should be 'ISO'. This makes dealing with the date output easier
		$this->query("SET timezone=".pg_escape_literal($conn, date_default_timezone_get()))->join();
		$this->query("SET datestyle='ISO'")->join();

		// This is the first connection, so make it the default one.
		if (self::$connection === null)
			self::$connection = $this;
	}

	/**
	 * This sets the default connection that would be returned by Connection::get()
	 */
	public static function setDefault(Connection $conn) : \void {
		self::$connection = $conn;
	}

	private \bool $in_transaction = false;
	private \Vector $savepoints = \Vector {};
	/**
	 * Starts an SQL transaction. If there is already a transaction in
	 * progress, this creates a savepoint instead that can be rolled back
	 * to.
	 */
	public function begin() : \void {
		if ($this->in_transaction) {
			$savepoint = "__savepoint_".($this->savepoints->count()+1);
			$this->savepoints->add($savepoint);
			$sp = $this->escapeIdentifier($savepoint);
			$this->query('SAVEPOINT '.$sp)->join();
		} else {
			$this->query('BEGIN')->join();
			$this->in_transaction = true;
		}
	}

	/**
	 * Sets the transaction mode as described at:
	 *     http://www.postgresql.org/docs/9.1/static/sql-set-transaction.html
	 */
	public function setTransactionMode(\string $mode) : \void {
		if ($this->in_transaction) {
			$this->query('SET TRANSACTION '.$mode)->join();
		}
	}

	/**
	 * Commits a transaction previously started with begin.
	 *
	 * If there is a currently valid savepoint it is released. The
	 * transaction itself is not actually committed until the last
	 * savepoint is gone.
	 *
	 * If the connection is not in a transaction, nothing happens.
	 */
	public function commit() : \void {
		if ($this->in_transaction) {
			if ($this->savepoints->count() > 0) {
				$savepoint = $this->savepoints->pop();
				$sp = $this->escapeIdentifier($savepoint);
				$this->query('RELEASE SAVEPOINT '.$sp)->join();
			} else {
				$this->query('COMMIT')->join();
				$this->in_transaction = false;
			}
		}
	}

	/**
	 * Rolls back the transaction or current savepoint.
	 *
	 * If the connection is not in a transaction, nothing happens.
	 */
	public function rollback() : \void {
		if ($this->in_transaction) {
			if ($this->savepoints->count() > 0) {
				$savepoint = $this->savepoints->pop();
				$sp = $this->escapeIdentifier($savepoint);
				$this->query('ROLLBACK TO SAVEPOINT '.$sp)->join();
			} else {
				$this->query('ROLLBACK')->join();
				$this->in_transaction = false;
			}
		}
	}

	/**
	 * Executes the given callable in a transaction, passing the connection
	 * through to the callable.
	 *
	 * This method returns the same thing as the callable
	 */
	public function inTransaction(\callable $fn) : \mixed {
		$this->begin();
		try {
			$ret = call_user_func($fn, $this);
		} catch (\Exception $e) {
			$this->rollback();
			throw $e;
		}
		$this->commit();
		return $ret;
	}

	private \bool $_sentRequest = false;
	private ?\resource $async_req = null;
	/**
	 * Send a single parameterized query to the database.
	 *
	 * This method returns an awaitable handle that returns a result.
	 * If multiple queries are supplied in $query, only the Result for the
	 * last one will be returned.
	 *
	 * NOTE: If multiple queries are sent in parallel, the first query will be
	 * serialized and the remaining queries will block. It is recommended to only
	 * send one query at a time.
	 *
	 * To send multiple queries use the multiQuery method
	 */
	public async function query(\string $query, array $params=[]) : Awaitable {
		if ($this->pg_conn == null)
			throw new DatabaseException("Database Connection closed");

		if ($this->_sentRequest) {
			if ($this->_sentRequest) {
				while ($test_res = pg_get_result($this->pg_conn)) {
					$this->async_req = $test_res;
				}
			}

			if (count($params) == 0) {
				$result = @pg_query($this->pg_conn, $query);
			} else {
				$result = @pg_query_params($this->pg_conn, $query, $params);
			}

			if (!$result)
				throw new ConnectionException($this, "Failed querying database");

			send_event("db::query", $query, $params);

			return Result::from_raw_result($result);
		} else {
			if (count($params) == 0) {
				if (!pg_send_query($this->pg_conn, $query)) {
					throw new ConnectionException($this, "Failed sending query");
				}
			} else {
				if (!pg_send_query_params($this->pg_conn, $query, $params)) {
					throw new ConnectionException($this, "Failed sending query");
				}
			}

			send_event("db::query", $query, $params);

			$this->_sentRequest = true;
			await \RescheduleWaitHandle::create(\RescheduleWaitHandle::QUEUE_DEFAULT, 5);
			$this->_sentRequest = false;

			$result = $this->async_req;
			$this->async_req = null;
			while ($test_res = pg_get_result($this->pg_conn)) {
				$result = $test_res;
			}

			if (!$result)
				throw new ConnectionException($this, "Failed querying database, no results found");

			return Result::from_raw_result($result);
		}
	}

	/**
	 * Sends multiple queries to the database returning an awaitable handle.
	 *
	 * The handle will return a vector of Results when joined.
	 *
	 * NOTE: This function cannot be used to send queries in parallel.
	 */
	public async function multiQuery(\string $query, array $params=[]) : Awaitable {
		if ($this->pg_conn == null)
			throw new DatabaseException("Database Connection closed");

		if ($this->_sentRequest) {
			throw new DatabaseException("Cannot use multiQuery in parallel with other queries");
		}

		if (count($params) == 0) {
			if (!pg_send_query($this->pg_conn, $query)) {
				throw new ConnectionException($this, "Failed sending query");
			}
		} else {
			if (!pg_send_query_params($this->pg_conn, $query, $params)) {
				throw new ConnectionException($this, "Failed sending query");
			}
		}

		send_event("db::query", $query, $params);

		$this->_sentRequest = true;
		await \RescheduleWaitHandle::create(\RescheduleWaitHandle::QUEUE_DEFAULT, 5);
		$this->_sentRequest = false;

		$results = \Vector {};

		while ($result = pg_get_result($this->pg_conn)) {
			$results->append(Result::from_raw_result($result));
		}

		return $results;
	}

	/**
	 * Gets the description of the last error that occured in the database
	 */
	public function getLastError() : \string {
		if ($this->pg_conn == null)
			throw new DatabaseException("Database Connection closed");
		return pg_last_error($this->pg_conn);
	}

	/**
	 * Escapes the given identifier according to postgres rules.
	 */
	public function escapeIdentifier(\string $id) : \string {
		if ($this->pg_conn == null)
			throw new DatabaseException("Database Connection closed");
		return pg_escape_identifier($this->pg_conn, $id);
	}

	/**
	 * Escapes the given value.
	 *
	 * If the value is an instance of `Type`, then the toDBString method is
	 * called on it.
	 * Types implementing Traversable will be converted to arrays.
	 * Everything else is escaped using the low-level pg_escape_literal
	 * function.
	 */
	public function escapeValue(\mixed $val, \bool $sub = false) : \string {
		if ($this->pg_conn == null)
			throw new DatabaseException("Database Connection closed");
		if ($val instanceof Type) {
			return $val->toDBString($this);
		} else if ($val instanceof Traversable) {
			if ($sub) {
				$s = '[';
			} else {
				$s = 'ARRAY[';
			}
			$comma = false;
			foreach ($val as $elem) {
				if ($comma) $s .= ',';
				$s .= $this->escapeValue($elem, true);
				$comma = true;
			}
			$s .= ']';
			return $s;
		} else if ($val === null) {
			return 'NULL';
		} elseif(is_bool($val)) {
			return $val ? 'true' : 'false';
		} else {
			return pg_escape_literal($this->pg_conn, $val);
		}
	}

	/**
	 * Closes the connection.
	 */
	public function close() : \void {
		if ($this->pg_conn) {
			pg_close($this->pg_conn);
			$this->pg_conn = null;
			send_event("db::close");
		}
	}
	/**
	 * Gets the raw underlying connection, only to be used
	 * by other ORM classes.
	 */
	public function _getRawConn() : \resource {
		return $this->pg_conn;
	}
}
