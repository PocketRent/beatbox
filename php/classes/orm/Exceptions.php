<?php

namespace beatbox\orm;

use beatbox\errors\Exception;

class DatabaseException extends Exception {
	public final function getEventPrefix() : string {
		return "db";
	}
}

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

class DeletedObjectException extends DatabaseException {
	public function __construct(\string $action, \string $object, $previous = null) {
		$message = "Tried to $action a deleted object '$object'";
		parent::__construct($message, 4, $previous);
	}
}
