<?hh // strict

namespace beatbox;

interface FragmentCallback {
	public function forFragment(\Indexish<int,string> $url, string $fragment) : mixed;
}
