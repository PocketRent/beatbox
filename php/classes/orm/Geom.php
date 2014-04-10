<?hh // strict

namespace beatbox\orm\geom;

use beatbox;
use beatbox\orm\Connection;
use beatbox\orm\TypeParseException;

abstract class GeomType implements beatbox\orm\Type {
	abstract static function fromString(string $val) : GeomType;
}

class point extends GeomType {
	public float $x;
	public float $y;

	public function __construct(float $x = 0.0, float $y = 0.0) {
		$this->x = $x;
		$this->y = $y;
	}

	public function toDBString(Connection $_unused) : string {
		return $this->__toString();
	}

	public function __toString() : string {
		return sprintf('(%f, %f)', $this->x, $this->y);
	}

	public static function fromString(string $val) : point {
		$parser = new _GeomParser($val);
		return self::parse($parser);
	}

	public static function parse(_GeomParser $parser) : point {
		$x = 0.0; $y = 0.0;
		$end_delim = false;
		if ($parser->eatChar('(')) $end_delim = true;
		list($worked, $x) = $parser->getFloat();
		if (!$worked)
			throw new TypeParseException('point', 'expected a float');
		if (!$parser->eatChar(','))
			throw new TypeParseException('point', 'expected a \',\'');
		list($worked, $y) = $parser->getFloat();
		if (!$worked)
			throw new TypeParseException('point', 'expected a float');
		if ($end_delim) {
			if (!$parser->eatChar(')'))
				throw new TypeParseException('point', 'expected a \')\'');
		}

		return new point($x, $y);
	}
}

class lseg extends GeomType {
	public point $start;
	public point $end;

	public function __construct(?point $start = null, ?point $end = null) {
		if ($start == null) $start = new point();
		if ($end == null) $end = new point();
		$this->start = $start;
		$this->end = $end;
	}

	public function toDBString(Connection $_unused) : string {
		return $this->__toString();
	}

	public function __toString() : string {
		return sprintf("[%s,%s]", $this->start, $this->end);
	}

	public static function fromString(string $val) : lseg {
		$parser = new _GeomParser($val);

		$delim = '';
		if ($parser->eatChar('[')) $delim = ']';
		else if ($parser->eatChar('(')) {
			// Make sure we don't accidentally eat the start of the point
			if ($parser->eatChar('(')) {
				$delim = ')';
			}
			// We need to go back because we have always eaten the start of the point
			// at this point
			$parser->goBack();
		}

		$start = point::parse($parser);

		if (!$parser->eatChar(','))
			throw new TypeParseException('lseg', 'expected a \',\'');

		$end = point::parse($parser);

		if ($delim) {
			if (!$parser->eatChar($delim))
				throw new TypeParseException('lseg', 'expected a \''.$delim.'\'');
		}

		return new lseg($start, $end);
	}
}

class box extends GeomType {
	public point $start;
	public point $end;

	public function __construct(?point $start = null, ?point $end = null) {
		if ($start == null) $start = new point();
		if ($end == null) $end = new point();
		$this->start = $start;
		$this->end = $end;
	}

	public function toDBString(Connection $_unused) : string {
		return $this->__toString();
	}

	public function __toString() : string {
		return sprintf("%s,%s", $this->start, $this->end);
	}

	public static function fromString(string $val) : box {
		$parser = new _GeomParser($val);

		$delim = false;

		if ($parser->eatChar('(')) {
			// Make sure we don't accidentally eat the start of the point
			if ($parser->eatChar('(')) {
				$delim = true;
			}
			// We need to go back because we have always eaten the start of the point
			// at this point
			$parser->goBack();
		}

		$start = point::parse($parser);

		if (!$parser->eatChar(','))
			throw new TypeParseException('box', 'expected a \',\'');

		$end = point::parse($parser);

		if ($delim) {
			if (!$parser->eatChar(')'))
				throw new TypeParseException('box', 'expected a \')\'');
		}

		return new box($start, $end);
	}
}

class path extends GeomType {
	public ImmVector<point> $points;
	public bool $open;

	public function __construct(?Traversable<point> $points = null, bool $open=false) {
		$this->points = new ImmVector($points);
		$this->open = $open;
	}

	public function toDBString(Connection $_unused) : string {
		return $this->__toString();
	}

	public function __toString() : string {
		$contents = bb_join(',', $this->points);
		if ($this->open) {
			return '['.$contents.']';
		} else {
			return '('.$contents.')';
		}
	}

	public static function fromString(string $val) : path {
		$parser = new _GeomParser($val);

		$delim = '';
		if ($parser->eatChar('[')) $delim = ']';
		else if ($parser->eatChar('(')) {
			// Make sure we don't accidentally eat the start of the point
			if ($parser->eatChar('(')) {
				$delim = ')';
			}
			// We need to go back because we have always eaten the start of the point
			// at this point
			$parser->goBack();
		}

		$pos = $parser->getPos();
		$vector = Vector {};

		try {
			$vector->add(point::parse($parser));
		} catch (TypeParseException $e) {
			$cur_pos = $parser->getPos();
			$parser->goBack(($cur_pos-$pos)-1);
			$delim = ')';
			$vector->add(point::parse($parser));
		}

		while ($parser->eatChar(',')) {
			$vector->add(point::parse($parser));
		}

		if ($delim) {
			if (!$parser->eatChar($delim))
				throw new TypeParseException('path', 'expected a \''.$delim.'\'');
		}

		return new path($vector, $delim == ']');
	}
}

class polygon extends GeomType {
	public ImmVector<point> $points;

	public function __construct(?Traversable<point> $points = null) {
		$this->points = new ImmVector($points);
	}

	public function toDBString(Connection $_unused) : string {
		return $this->__toString();
	}

	public function __toString() : string {
		return '('.bb_join(',', $this->points).')';
	}

	public static function fromString(string $val) : polygon {
		$parser = new _GeomParser($val);

		$delim = '';
		if ($parser->eatChar('(')) {
			// Make sure we don't accidentally eat the start of the point
			if ($parser->eatChar('(')) {
				$delim = ')';
			}
			// We need to go back because we have always eaten the start of the point
			// at this point
			$parser->goBack();
		}

		$pos = $parser->getPos();
		$vector = Vector {};

		try {
			$vector->add(point::parse($parser));
		} catch (TypeParseException $e) {
			$cur_pos = $parser->getPos();
			$parser->goBack(($cur_pos-$pos)-1);
			$delim = ')';
			$vector->add(point::parse($parser));
		}

		while ($parser->eatChar(',')) {
			$vector->add(point::parse($parser));
		}

		if ($delim) {
			if (!$parser->eatChar($delim))
				throw new TypeParseException('path', 'expected a \''.$delim.'\'');
		}

		return new polygon($vector);
	}
}

class circle extends GeomType {
	public point $center;
	public float $radius;

	public function __construct(?point $center = null, float $radius = 1.0) {
		if ($center == null) $this->center = new point();
		else $this->center = $center;
		$this->radius = $radius;
	}

	public function toDBString(Connection $_unused) : string {
		return $this->__toString();
	}

	public function __toString() : string {
		return sprintf("<%s, %f>", $this->center, $this->radius);
	}

	public static function fromString(string $val) : circle {
		$parser = new _GeomParser($val);

		$delim = '';
		if ($parser->eatChar('<')) $delim = '>';
		else if ($parser->eatChar('(')) {
			if ($parser->eatChar('(')) {
				$delim = ')';
			}

			$parser->goBack();
		}

		$point = point::parse($parser);
		if (!$parser->eatChar(','))
			throw new TypeParseException('circle', 'expected a \',\'');

		list($worked, $radius) = $parser->getFloat();
		if (!$worked)
			throw new TypeParseException('circle', 'expected a float');

		if ($delim) {
			if (!$parser->eatChar($delim))
				throw new TypeParseException('circle', 'expected a \''.$delim.'\'');
		}

		return new circle($point, $radius);
	}
}

class _GeomParser {
	private string $str;
	private int $pos = 0;

	public function __construct(string $s) {
		$this->str = $s;
	}

	public function peekChar(int $n=0) : ?string {
		$pos = $this->pos + $n;
		if ($pos < strlen($this->str)) {
			return $this->str[$pos];
		} else {
			return null;
		}
	}

	public function goBack(int $n=1): void {
		if ($n < 1) return;
		if ($n > $this->pos) $n = $this->pos;

		$this->pos -= $n;
	}

	public function getPos() : int {
		return $this->pos;
	}

	public function eatChar(string $c) : bool {
		$this->skipWhitespace();
		$c = $c[0];
		if ($this->pos < strlen($this->str) && $this->str[$this->pos] == $c) {
			$this->pos++;
			return true;
		} else {
			return false;
		}
	}

	public function getFloat() : (bool, float) {
		$this->skipWhitespace();
		$start = $this->pos;
		$seen_point = false;

		$len = strlen($this->str);
		while ($this->pos < $len) {
			if (ctype_digit($this->str[$this->pos])) {
				$this->pos++;
			} else if ($this->str[$this->pos] == '.' && !$seen_point) {
				$seen_point = true;
				$this->pos++;
			} else {
				break;
			}
		}

		if ($start == $this->pos) return tuple(false, 0.0);

		$str = substr($this->str, $start, $this->pos-$start);

		$num = (float)$str;
		return tuple(true, $num);
	}

	public function skipWhitespace(): void {
		$len = strlen($this->str);
		while ($this->pos < $len && ctype_space($this->str[$this->pos]))
			$this->pos++;
	}
}
