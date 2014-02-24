<?hh // strict

namespace beatbox;

class UnzipIterator<Tk,Tv> implements KeyedIterator<Tk,Tv> {
	private Iterator<Pair<Tk, Tv>> $i;

	public function __construct(Iterator<Pair<Tk, Tv>> $i) {
		$this->i = $i;
	}

	public function current() : Tv {
		return $this->i->current()[1];
	}

	public function next() : void {
		$this->i->next();
	}

	public function rewind() : void {
		$this->i->rewind();
	}

	public function valid() : bool {
		return $this->i->valid();
	}

	public function key() : Tk {
		return $this->i->current()[0];
	}
}

class UnzipIterable<Tk, Tv> implements \KeyedIterable<Tk, Tv> {
	use \LazyKeyedIterable<Tk, Tv>;

	private Iterable<Pair<Tk, Tv>> $iterable;

	public function __construct(Iterable<Pair<Tk, Tv>> $iterable) {
		$this->iterable = $iterable;
	}

	public function getIterator() : UnzipIterator<Tk, Tv> {
		return new UnzipIterator($this->iterable->getIterator());
	}
}
