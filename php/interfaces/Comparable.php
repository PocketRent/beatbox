<?hh

namespace beatbox;

interface Comparable {
	// Returns a value that is less than, equal to or greater than
	// zero if $this is less than, equal to or greater than $other,
	// respectively
	public function cmp($other) : \int;

	public function eq($other) : \bool;
	public function ne($other) : \bool;

	public function lt($other) : \bool;
	public function le($other) : \bool;

	public function gt($other) : \bool;
	public function ge($other) : \bool;
}
