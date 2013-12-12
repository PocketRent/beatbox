<?hh

namespace beatbox\orm;

use \beatbox;

abstract class CompositeType implements Type {
	abstract static function fromString(\string $val) : CompositeType;
}

class DateTimeType extends \DateTime implements Type {
	use beatbox\Compare;

	public final static function fromDateTime(\DateTime $date) : DateTimeType {
		if ($date instanceof DateTimeType) return clone $date;
		$date_type = new self($date->format('@U')); // The unix epoch is always UTC
		return $date_type->setTimezone($date->getTimezone());
	}

	private \int $infinity = 0;

	/**
	 * Construct a new DateTimeType, doesn't take a timezone like
	 * DateTime, because timestamps from the database have the same
	 * timezone as the local PHP default timezone
	 */
	public function __construct(\mixed $date) {
		if ($date == 'infinity' || $date == '+infinity') {
			$this->infinity = 1;
			parent::__construct('9999-12-31 23:59:59');
		} else if ($date == '-infinity') {
			$this->infinity = -1;
			parent::__construct('-9999-01-01 00:00:00');
		} else {
			parent::__construct($date);
			assert($this->getTimezone()->getName() == date_default_timezone_get());
		}
	}

	/**
	 * Sets the timezone, accepts a string timezone (like 'Pacific/Auckland')
	 * or a DateTimeZone object
	 *
	 * Set to null to clear the timezone (Actually just resets it back to default)
	 */
	public function setTimezone(\mixed $timezone) : ?DateTimeType {
		if ($timezone === null) {
			if (parent::setTimezone(new \DateTimeZone(date_default_timezone_get()))) {
				return $this;
			} else {
				return false;
			}
		}
		if (!($timezone instanceof \DateTimeZone)) {
			if (is_string($timezone)) {
				$timezone = new \DateTimeZone($timezone);
			} else {
				throw new \InvalidArgumentException("\$timezone should be string or instance of DateTimeZone");
			}
		}

		if (parent::setTimezone($timezone)) {
			return $this;
		} else {
			return false;
		}
	}

	public function setTime(\int $hour, \int $minute, \int $second=0) : ?DateTimeType {
		$this->infinity = 0;
		return parent::setTime($hour, $minute, $second);
	}

	public function setDate(\int $year, \int $month, \int $day) : ?DateTimeType {
		$this->infinity = 0;
		return parent::setDate($year, $month, $day);
	}

	////// Predicates

	public function isPositiveInfinity() : \bool { return $this->infinity == 1; }
	public function isNegativeInfinity() : \bool { return $this->infinity == 1; }
	public function isInfinite() : \bool { return $this->infinity != 0; }

	public function setPositiveInfinity() : \void {
		// Set the date to something way in the future, to make it easier
		// to spot bad code
		$this->setDate(9999, 12, 31); $this->setTime(23,59,59);
		$this->infinity = 1;
	}
	public function setNegativeInfinity() : \void {
		// Set the date to something way in the past, to make it easier to
		// spot bad code
		$this->setDate(-9999, 1, 1); $this->setTime(0,0,0);
		$this->infinity = -1;
	}

	/**
	 * Returns the date in a format suitable for the database.
	 * The format is similar to ISO 8601, with some minor variation.
	 *
	 * Example: 2012-06-13 12:11:30 UTC
	 */
	public function toDBString(?Connection $conn=null) : \string {
		return $conn->escapeValue($this->__toString());
	}

	public function __toString() : \string {
		if ($this->infinity == 1) {
			return 'infinity';
		} else if ($this->infinity == -1) {
			return '-infinity';
		} else {
			return $this->format('Y-m-d H:i:s e');
		}
	}

	// Compare trait cmp function
	public function cmp(\DateTime $other) : \int {
		if ($other instanceof DateTimeType) {
			// other is a DateTimeType, check for infinities
			if ($this->isInfinite() || $other->isInfinite()) {
				return $this->infinity - $other->infinity;
			}
		}
		if ($this->isInfinite()) {
			// This is infinity, so just return which infinity it is
			return $this->infinity;
		}

		$this_ts = $this->getTimestamp();
		$other_ts = $other->getTimestamp();

		return $this_ts - $other_ts;
	}

	public function inPast() : \bool {
		return $this->cmp(new \DateTime('now')) < 0;
	}

	public function inFuture() : \bool {
		return $this->cmp(new \DateTime('now')) > 0;
	}
}
