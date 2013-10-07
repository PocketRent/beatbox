<?php

namespace pr\base;

interface FragmentCallback {
	public function forFragment(\Traversable $url, \string $fragment);
}
