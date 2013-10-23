<?hh

namespace beatbox;

trait Compare {
	// Returns a value that is less than, equal to or greater than
	// zero if $this is less than, equal to or greater than $other,
	// respectively
	abstract function cmp($other);

	public function eq($other) { return $this->cmp($other) == 0; }
	public function ne($other) { return $this->cmp($other) != 0; }

	public function lt($other) { return $this->cmp($other) <  0; }
	public function le($other) { return $this->cmp($other) <= 0; }

	public function gt($other) { return $this->cmp($other) >  0; }
	public function ge($other) { return $this->cmp($other) >= 0; }
}
