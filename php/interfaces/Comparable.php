<?hh // strict

namespace beatbox;

interface Comparable {
	// Returns a value that is less than, equal to or greater than
	// zero if $this is less than, equal to or greater than $other,
	// respectively
	public function cmp(\mixed $other) : \int;

	public function eq(\mixed $other) : \bool;
	public function ne(\mixed $other) : \bool;

	public function lt(\mixed $other) : \bool;
	public function le(\mixed $other) : \bool;

	public function gt(\mixed $other) : \bool;
	public function ge(\mixed $other) : \bool;
}
