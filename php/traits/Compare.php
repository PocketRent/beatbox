<?hh // strict

namespace beatbox;

trait Compare implements Comparable {
	public function eq(\mixed $other) : \bool { return $this->cmp($other) == 0; }
	public function ne(\mixed $other) : \bool { return $this->cmp($other) != 0; }

	public function lt(\mixed $other) : \bool { return $this->cmp($other) <  0; }
	public function le(\mixed $other) : \bool { return $this->cmp($other) <= 0; }

	public function gt(\mixed $other) : \bool { return $this->cmp($other) >  0; }
	public function ge(\mixed $other) : \bool { return $this->cmp($other) >= 0; }
}
