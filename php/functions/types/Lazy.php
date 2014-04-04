<?hh // strict

namespace {
	function make_lazy<T>(T $value): beatbox\Lazy<T> {
		return function(): ?T use($value) { return $value; };
	}
}

namespace beatbox {
	type Lazy<T> = (function():?T);
}
