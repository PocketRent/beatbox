<?hh

namespace beatbox;

use HH\Traversable;

interface FragmentCallback {
	public function forFragment(Traversable $url, \string $fragment);
}
