<?hh

namespace beatbox;

trait Compare {
	// Returns a value that is less than, equal to or greater than
	// zero if $this is less than, equal to or greater than $other,
	// respectively
	abstract function cmp(\mixed $other) : \int;

	public function eq(\mixed $other) : \bool { return $this->cmp($other) == 0; }
	public function ne(\mixed $other) : \bool { return $this->cmp($other) != 0; }

	public function lt(\mixed $other) : \bool { return $this->cmp($other) <  0; }
	public function le(\mixed $other) : \bool { return $this->cmp($other) <= 0; }

	public function gt(\mixed $other) : \bool { return $this->cmp($other) >  0; }
	public function ge(\mixed $other) : \bool { return $this->cmp($other) >= 0; }
}
