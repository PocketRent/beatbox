<?hh

function wait<T>(Awaitable<T> $handle) : T {
	return $handle->getWaitHandle()->join();
}

function gena(array $a) : Awaitable<array> {
	return GenArrayWaitHandle::create(array_map($x ==> $x->getWaitHandle(), $a));
}

function genva(...) : Awaitable<array> {
	return gena(func_get_args());
}

async function gen_foreach_v<Ta,Tb>(Traversable<Ta> $t,
	(function (Ta) : Awaitable<void>) $f) : Awaitable<void> {

	$handles = Vector {};
	foreach ($t as $v) {
		$handles->add($f($v)->getWaitHandle());
	}

	await GenVectorWaitHandle::create($handles);
}

async function gen_foreach_kv<Tk,Tv,Tr>(KeyedTraversable<Tk,Tv> $t,
	(function (Tk, Tv) : Awaitable<Tr>) $f) : Awaitable<void> {

	$handles = Vector {};
	foreach ($t as $k => $v) {
		$handles->add($f($k, $v)->getWaitHandle());
	}

	await GenVectorWaitHandle::create($handles);
}
