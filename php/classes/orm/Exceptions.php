<?hh

namespace beatbox\orm;

use beatbox\errors\Exception;

/**
 * Common exception for database-related errors
 */
class DatabaseException extends Exception {
	public final function getEventPrefix() : \string {
		return "db";
	}
}

/**
 * Exception for errors that occur with the connection itself. For example,
 * being unable to connect
 */
class ConnectionException extends DatabaseException {
	protected \string $dbError;

	public function __construct(\resource $conn, \string $message, ?\Exception $previous=null) {
		$this->dbError = pg_last_error($conn) ?: "unknown error";
		$message = $message . ": '" . $this->dbError . "'";
		parent::__construct($message, 1, $previous);
	}

	public function dbError() : \string {
		return $this->dbError;
	}
}

/**
 * Exception for errors that occur with results and result sets.
 */
class ResultException extends DatabaseException {
	public function __construct(\resource $result, \string $message="",
								?\Exception $previous=null) {
		$err = pg_result_error($result);
		if ($message != "") {
			$message = $message . ": '" . ($err ?: "unknown error") . "'";
		} else {
			$message = $err ?: "unknown error";
		}

		parent::__construct($message, 2, $previous);
	}
}

/**
 * Exception thrown when an invalid field is referenced.
 */
class InvalidFieldException extends DatabaseException {
	private static int $num_fields = 4; // number of valid fields to show in the error message

	public function __construct(\string $field, \ConstSet<string> $valid_fields,
								?\Exception $previous=null) {
		$trunc = 0;
		if ($valid_fields->count() > self::$num_fields) {
			$trunc = $valid_fields->count() - self::$num_fields;
			$valid_fields = Vector::slice(Vector::fromItems($valid_fields), 0, self::$num_fields);
		}
		$valid_fields = $valid_fields->map($val ==> "'$val'");
		$message = "Invalid field '$field', expected one of [".bb_join(', ', $valid_fields);
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
	public function __construct(\string $action, \string $object, ?\Exception $previous = null) {
		$message = "Tried to $action a deleted object '$object'";
		parent::__construct($message, 4, $previous);
	}
}

class TypeParseException extends DatabaseException {
	public function __construct(\string $type, \string $message, ?\Exception $previous = null) {
		$message = "Failed while parsing $type: '$message'";
		parent::__construct($message, 5, $previous);
	}
}

class InvalidValueException extends DatabaseException {
	public function __construct(\string $val, ?\Exception $previous = null) {
		$message = "Invalid database value: '$val'";
		parent::__construct($message, 6, $previous);
	}
}
