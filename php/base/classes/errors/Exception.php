<?php

namespace pr\base\errors;

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

	public function __construct($message, $code=0, $previous=null) {
		parent::__construct($message, $code+self::MIN_EXCEPTION_CODE, $previous);
	}

	public function getBaseCode() {
		return $this->getCode() - self::MIN_EXCEPTION_CODE;
	}
}
