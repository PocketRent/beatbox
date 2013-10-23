<?hh

namespace beatbox;

interface FragmentCallback {
	public function forFragment(\Traversable $url, \string $fragment);
}
