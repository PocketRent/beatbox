<?hh

namespace beatbox\orm;

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
	const CONNECTION_POLL_TIME = 1000;//us

	private static ?Connection $connection=null;

	private Vector<resource> $connection_pool = Vector {};
	private string $connection_string;
	private ?resource $transactionConn = null;

	/**
	 * Returns the singleton instance of Connection, creating it if necessary.
	 */
	public static function get() : Connection {
		if (self::$connection === null) {
			// The constructor will set self::$connection
			return new static();
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
	public function __construct(?array $params = null) {
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

		$this->connection_string = $str;

		// This is the first connection, so make it the default one.
		if (self::$connection === null)
			self::$connection = $this;

	}

	/**
	 * This sets the default connection that would be returned by Connection::get()
	 */
	public static function setDefault(Connection $conn) : void {
		self::$connection = $conn;
	}

	public async function withRawConn<T>((function (resource) : Awaitable<T>) $fn) : Awaitable<T> {
		$conn = null;
		if ($this->in_transaction) {
			if ($this->transactionConn == null) {
				if ($this->connection_pool->count() == 0) {
					$this->transactionConn = $this->newConnection();
				} else {
					$this->transactionConn = $this->connection_pool->pop();
				}
			}
			$conn = $this->transactionConn;
		} else {
			if ($this->connection_pool->count() == 0) {
				$conn = $this->newConnection();
				$this->connection_pool->add($conn);
			}

			$conn = $this->connection_pool->pop();
		}

		try {
			$val = await $fn($conn);
		} finally {
			if (!$this->in_transaction) {
				$this->connection_pool->add($conn);
			}
		}

		return $val;
	}

	private function newConnection() : resource {
		assert($this->connection_pool->count() == 0);
		$conn = @pg_connect($this->connection_string);

		if (!$conn) {
			throw new DatabaseException("Failed to connect to database");
		}

		if (pg_connection_status($conn) != PGSQL_CONNECTION_OK) {
			throw new DatabaseExcept("Failed to connect to database");
		}

		send_event("db::connect");

		// Set some connection options, the timezone is set to the local default one
		// DateStyle should be 'ISO'. This makes dealing with the date output easier
		pg_query($conn, "SET timezone=".pg_escape_literal($conn, date_default_timezone_get()));
		pg_query($conn, "SET datestyle='ISO'");

		return $conn;
	}

	private bool $in_transaction = false;
	private Vector $savepoints = Vector {};
	/**
	 * Starts an SQL transaction. If there is already a transaction in
	 * progress, this creates a savepoint instead that can be rolled back
	 * to.
	 */
	public function begin() : void {
		if ($this->in_transaction) {
			$savepoint = "__savepoint_".($this->savepoints->count()+1);
			$this->savepoints->add($savepoint);
			$sp = $this->escapeIdentifier($savepoint);
			wait($this->query('SAVEPOINT '.$sp));
		} else {
			$this->in_transaction = true;
			wait($this->query('BEGIN'));
		}
	}

	/**
	 * Sets the transaction mode as described at:
	 *     http://www.postgresql.org/docs/9.1/static/sql-set-transaction.html
	 */
	public function setTransactionMode(string $mode) : void {
		if ($this->in_transaction) {
			wait($this->query('SET TRANSACTION '.$mode));
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
	public function commit() : void {
		if ($this->in_transaction) {
			if ($this->savepoints->count() > 0) {
				$savepoint = $this->savepoints->pop();
				$sp = $this->escapeIdentifier($savepoint);
				wait($this->query('RELEASE SAVEPOINT '.$sp));
			} else {
				wait($this->query('COMMIT'));
				$this->in_transaction = false;
				$this->connection_pool->add(nullthrows($this->transactionConn));
				$this->transactionConn = null;
			}
		}
	}

	/**
	 * Rolls back the transaction or current savepoint.
	 *
	 * If the connection is not in a transaction, nothing happens.
	 */
	public function rollback() : void {
		if ($this->in_transaction) {
			if ($this->savepoints->count() > 0) {
				$savepoint = $this->savepoints->pop();
				$sp = $this->escapeIdentifier($savepoint);
				wait($this->query('ROLLBACK TO SAVEPOINT '.$sp));
			} else {
				wait($this->query('ROLLBACK'));
				$this->in_transaction = false;
				$this->connection_pool->add(nullthrows($this->transactionConn));
				$this->transactionConn = null;
			}
		}
	}

	/**
	 * Executes the given callable in a transaction, passing the connection
	 * through to the callable.
	 *
	 * This method returns the same thing as the callable
	 */
	public function inTransaction<T>((function (Connection) : T) $fn) : T {
		$this->begin();
		try {
			$ret = $fn($this);
		} catch (\Exception $e) {
			$this->rollback();
			throw $e;
		}
		$this->commit();
		return $ret;
	}

	/**
	 * Send a single parameterized query to the database.
	 *
	 * This method returns an awaitable handle that returns a result.
	 * If multiple queries are supplied in $query, only the Result for the
	 * last one will be returned.
	 *
	 * To send multiple queries use the multiQuery method
	 */
	public async function query(string $query, array $params=[]) : Awaitable<Result> {
		return await $this->withRawConn(async function ($conn) use ($query, $params) {
			if (count($params) == 0) {
				if (!pg_send_query($conn, $query)) {
					throw new ConnectionException($conn, "Failed sending query");
				}
			} else {
				if (!pg_send_query_params($conn, $query, $params)) {
					throw new ConnectionException($conn, "Failed sending query");
				}
			}

			send_event("db::query", $query, $params);

			if (!$this->in_transaction) {
				while (pg_connection_busy($conn)) {
					await SleepWaitHandle::create(self::CONNECTION_POLL_TIME);
				}
			}

			$result = null;
			while ($test_res = pg_get_result($conn)) {
				$result = $test_res;
			}

			if (!$result)
				throw new ConnectionException($conn, "Failed querying database, no results found");

			return Result::from_raw_result($result);
		});
	}

	/**
	 * Sends multiple queries to the database returning an awaitable handle.
	 *
	 * The handle will return a vector of Results when joined.
	 */
	public async function multiQuery(string $query, array $params=[]) : Awaitable<Vector<Result>> {

		return await $this->withRawConn(async function ($conn) use ($query, $params) {
			if (count($params) == 0) {
				if (!pg_send_query($conn, $query)) {
					throw new ConnectionException($conn, "Failed sending query");
				}
			} else {
				if (!pg_send_query_params($conn, $query, $params)) {
					throw new ConnectionException($conn, "Failed sending query");
				}
			}

			send_event("db::query", $query, $params);

			if (!$this->in_transaction) {
				while (pg_connection_busy($conn)) {
					await SleepWaitHandle::create(self::CONNECTION_POLL_TIME);
				}
			}

			$results = Vector {};
			while ($result = pg_get_result($conn)) {
				$results->add(Result::from_raw_result($result));
			}

			return $results;
		});
	}

	private ?QueryQueue $queue = null;
	public function queueQuery(string $query) : Awaitable<Result> {
		if ($this->queue == null) {
			$this->queue = new QueryQueue($this);
		}
		return $this->queue->add($query);
	}

	public function clearQueue() {
		$this->queue = null;
	}

	/**
	 * Escapes the given identifier according to postgres rules.
	 */
	public function escapeIdentifier(string $id) : string {
		return wait($this->withRawConn(async function ($conn) : Awaitable<string> use ($id) {
			return pg_escape_identifier($conn, $id);
		}));
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
	public function escapeValue(mixed $val, bool $sub = false) : string {
		if ($val instanceof Type) {
			return $val->toDBString($this);
		} else if ($val instanceof Map) {
			$s = '';
			$comma = false;
			foreach ($val as $k => $v) {
				if ($comma) $s .= ',';
				$s .= $this->hstoreEscape((string)$k);
				$s .= '=>';
				if ($v === null) {
					$s .= 'NULL';
				} else {
					$s .= $this->hstoreEscape((string)$v);
				}
				$comma = true;
			}
			return $this->escapeValue($s);
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
		} else if(is_bool($val)) {
			return $val ? 'true' : 'false';
		} else {
			return wait($this->withRawConn(async function ($conn) : Awaitable<string> use ($val) {
				return pg_escape_literal($conn, (string)$val);
			}));
		}
	}

    public function escapeBytea(?string $bytea): string {
        if ($bytea === null) return 'NULL';
        return wait($this->withRawConn(async function ($conn) : Awaitable<string> use ($bytea) {
            return "'".pg_escape_bytea($conn, $bytea)."'";
        }));
    }

    public function unescapeBytea(?string $str): ?string {
        if ($str === null) return null;
        return pg_unescape_bytea($str);
    }

	private function hstoreEscape(string $s) : string {
		$replacements = [
			'\\' => '\\\\',
			'"' => '\"',
		];
		$s = strtr($s, $replacements);
		return '"'.$s.'"'; // Always double quote, even if it's not necessary
	}

	public function close() {
		$this->connection_string = "";
		foreach ($this->connection_pool as $conn) {
			pg_close($conn);
		}
		$this->connection_pool = Vector {};
		if ($this->transactionConn !== null) {
			pg_close($this->transactionConn);
			$this->transactionConn = null;
		}
	}
}

class QueryQueue {
	private Vector<string> $queries = Vector {};
	private Connection $conn;
	private ?Vector<Result> $results = null;

	public function __construct(Connection $conn) {
		$this->conn = $conn;
	}

	public function add(string $query) : Awaitable<Result> {
		$queryNum = $this->queries->count();
		$this->queries->add($query);
		return new QueuedQuery($this, $queryNum);
	}

	public async function getResult(int $num) : Awaitable<Result> {
		if ($this->results == null) {
			try {
				$query = bb_join(';', $this->queries);
				$this->results = await $this->conn->multiQuery($query);
			} finally {
				$this->conn->clearQueue();
			}
		}

		$results = nullthrows($this->results);
		return $results[$num];
	}
}

class QueuedQuery implements Awaitable<Result> {
	private QueryQueue $queue;
	private int $queryNum;

	public function __construct(QueryQueue $queue, int $queryNum) {
		$this->queue = $queue;
		$this->queryNum = $queryNum;
	}

	public function getWaitHandle() : WaitHandle<Result> {
		$do = async function (QueryQueue $queue, int $num) : Awaitable<Result> {
			return await $queue->getResult($num);
		};

		return $do($this->queue, $this->queryNum)->getWaitHandle();
	}
}
