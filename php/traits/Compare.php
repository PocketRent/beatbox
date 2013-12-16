<?hh

namespace beatbox;

trait Compare implements Comparable {
	// Returns a value that is less than, equal to or greater than
	// zero if $this is less than, equal to or greater than $other,
	// respectively
	// abstract public function cmp(T $other) : \int;

	public function eq($other) : \bool { return $this->cmp($other) == 0; }
	public function ne($other) : \bool { return $this->cmp($other) != 0; }

	public function lt($other) : \bool { return $this->cmp($other) <  0; }
	public function le($other) : \bool { return $this->cmp($other) <= 0; }

	public function gt($other) : \bool { return $this->cmp($other) >  0; }
	public function ge($other) : \bool { return $this->cmp($other) >= 0; }
}
