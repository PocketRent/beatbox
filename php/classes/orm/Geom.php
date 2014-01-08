<?hh

namespace beatbox\orm\geom;

use \beatbox;

abstract class GeomType implements Type {
	abstract static function fromString(\string $val) : GeomType;
}

class point extends GeomType {
	public \float $x;
	public \float $y;

	public function __construct(\float $x = 0.0, \float $y = 0.0) {
		$this->x = $x;
		$this->y = $y;
	}

	public function toDBString(Connection $_unused) : \string {
		return $this->__toString();
	}

	public function __toString() : \string {
		return sprintf('(%f, %f)', $this->x, $this->y);
	}

	public static function fromString(\string $val) : point {
		if ($val[0] == '(') $val = substr($val, 1, -1);

		list($x, $y) = explode(',', $val, 2);

		$x = trim($x); $y = trim($y);

		return new point((float)$x, (float)$y);
	}
}
