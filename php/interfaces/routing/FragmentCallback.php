<?hh // strict

namespace beatbox;

interface FragmentCallback {
	public function forFragment(Path $url, string $fragment) : mixed;
}
