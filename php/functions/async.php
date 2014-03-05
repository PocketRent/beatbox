<?hh // strict

function wait<T>(Awaitable<T> $handle) : T {
	return $handle->getWaitHandle()->join();
}

function gena(array $a) : Awaitable<array> {
	return GenArrayWaitHandle::create(array_map($x ==> $x->getWaitHandle(), $a));
}

function genva(...) : Awaitable<array> {
	return gena(func_get_args());
}
