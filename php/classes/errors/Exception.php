<?hh // strict

namespace beatbox\errors;

class Exception extends \Exception {
	/**
	 * Minimum exception code, this is added to all the codes
	 * that go through this exception, to help distinguish them
	 * from other exceptions.
	 */
	const MIN_EXCEPTION_CODE = 10000;

	/**
	 * Returns a prefix for the event logger to prepend
	 */
	public function getEventPrefix() : string {
		return "";
	}

	public function __construct(string $message, int $code=0, ?\Exception $previous=null) {
		parent::__construct($message, $code+self::MIN_EXCEPTION_CODE, $previous);
	}

	public function getBaseCode() : int {
		return $this->getCode() - self::MIN_EXCEPTION_CODE;
	}
}

/**
 * Used for providing a body in stubs so the Hack typechecker is
 * still useful.
 */
class UnimplementedException extends Exception {
	public function __construct() {
		// Quite hacky, but use `debug_backtrace` to get information of the
		// calling function
		// UNSAFE -- it doesn't much like the DEBUG_BACKTRACE_IGNORE_ARGS
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		$frame = $backtrace[1];

		$func = $frame['function'];
		$type = 'function';
		if (array_key_exists('class', $frame)) {
			$func = sprintf("%s::%s", $frame['class'], $func);
			$type = 'method';
		}

		$msg = sprintf("Unimplemented %s '%s' called", $type, $func);
		parent::__construct($msg);
	}
}
