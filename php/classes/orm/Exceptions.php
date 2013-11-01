<?hh

namespace beatbox\orm;

use beatbox\errors\Exception;

/**
 * Common exception for database-related errors
 */
class DatabaseException extends Exception {
	public final function getEventPrefix() : string {
		return "db";
	}
}

/**
 * Exception for errors that occur with the connection itself. For example,
 * being unable to connect
 */
class ConnectionException extends DatabaseException {
	protected $dbError;

	public function __construct(Connection $conn, $message, $previous=null) {
		$this->dbError = $conn->getLastError();
		$message = $message . ": '" . $this->dbError . "'";
		parent::__construct($message, 1, $previous);
	}

	public function dbError() {
		return $this->dbError;
	}
}

/**
 * Exception for errors that occur with results and result sets.
 */
class ResultException extends DatabaseException {
	public function __construct($result, $message="", $previous=null) {
		$err = pg_result_error($result);
		if ($message != "") {
			$message = $message . ": '" . $err . "'";
		} else {
			$message = $err;
		}

		parent::__construct($message, 2, $previous);
	}
}

/**
 * Exception thrown when an invalid field is referenced.
 */
class InvalidFieldException extends DatabaseException {
	private static $num_fields = 4; // number of valid fields to show in the error message

	public function __construct(\string $field, $valid_fields, $previous=null) {
		$trunc = 0;
		if ($valid_fields->count() > self::$num_fields && $valid_fields instanceof \Vector) {
			$trunc = $valid_fields->count() - self::$num_fields;
			$valid_fields = \Vector::slice($valid_fields, 0, self::$num_fields);
		}
		$valid_fields = $valid_fields->map(function ($val) { return "'$val'"; });
		$message = "Invalid field '$field', expected one of [".pr_join(', ', $valid_fields);
		if ($trunc > 0) {
			$message .= ",.. and $trunc others]";
		} else {
			$message .= "]";
		}

		parent::__construct($message, 3, $previous);
	}
}

/**
 * Exception thrown when trying to perform an action on a deleted object.
 */
class DeletedObjectException extends DatabaseException {
	public function __construct(\string $action, \string $object, $previous = null) {
		$message = "Tried to $action a deleted object '$object'";
		parent::__construct($message, 4, $previous);
	}
}
