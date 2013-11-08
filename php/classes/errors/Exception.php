<?hh

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
	public function getEventPrefix() : \string {
		return "";
	}

	public function __construct(\string $message, \int $code=0, \Exception $previous=null) {
		parent::__construct($message, $code+self::MIN_EXCEPTION_CODE, $previous);
	}

	public function getBaseCode() : \int {
		return $this->getCode() - self::MIN_EXCEPTION_CODE;
	}
}
