<?php

namespace beatbox\test\constraint;

/**
 * Constraint for objects that have a cmp method
 */
class Compare extends \PHPUnit_Framework_Constraint {
	private $obj = null;
	private $type = 0;

	public function __construct($expected, $type) {
		$this->obj = $expected;
		$this->type = $type;
	}

	public function matches($other) {
		$res = $this->obj->cmp($other);

		if ($this->type == 0) return $res == 0;
		if ($this->type > 0) return $res > 0;
		if ($this->type < 0) return $res < 0;
	}

	public function failureDescription($other) {
		if (method_exists($other, '__toString'))
			$val = (string)$other;
		else
			$val = \PHPUnit_Util_Type::export($other);

		return $val.' '.$this->toString();
	}

	public function toString() {
		if (method_exists($this->obj, '__toString'))
			$val = (string)$this->obj;
		else
			$val = \PHPUnit_Util_Type::export($this->obj);

		if ($this->type == 0)
			return sprintf('is equal to expected %s', $val);
		if ($this->type > 0)
			return sprintf('is greater than expected %s', $val);
		if ($this->type < 0)
			return sprintf('is less than expected %s', $val);
	}
}
