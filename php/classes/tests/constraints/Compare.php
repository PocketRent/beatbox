<?hh

namespace beatbox\test\constraint;

/**
 * Constraint for objects that have a cmp method
 */
class Compare extends \PHPUnit_Framework_Constraint {
	private ?\mixed $obj = null;
	private \int $type = 0;

	public function __construct(\mixed $expected, \int $type) {
		$this->obj = $expected;
		$this->type = $type;
	}

	public function matches(\mixed $other) : \bool {
		$res = $this->obj->cmp($other);

		if ($this->type == 0) return $res == 0;
		if ($this->type > 0) return $res > 0;
		if ($this->type < 0) return $res < 0;
	}

	public function failureDescription(\mixed $other) : \string {
		if (method_exists($other, '__toString'))
			$val = (string)$other;
		else
			$val = \PHPUnit_Util_Type::export($other);

		return $val.' '.$this->toString();
	}

	public function toString() : \string {
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
