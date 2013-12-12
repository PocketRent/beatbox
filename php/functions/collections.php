<?hh

/**
 * Recursively merge one or more maps.
 *
 * Behaves in a similar manner to array_merge_recusrive
 */
function map_merge_recursive(ConstMapAccess $base/*, ... */) : Map {
	$ret = new Map;

	$ret->setAll($base);

	$args = func_get_args();
	array_shift($args);

	foreach($args as $extra) {
		if(!$extra instanceof ConstMapAccess) {
			user_error(__FUNCTION__ . ' passed an argument that does not implement ConstMapAccess', E_USER_ERROR);
		}
		foreach($extra as $k => $v) {
			if(isset($ret[$k])) {
				// Recurse if both sides are maps
				if($ret[$k] instanceof ConstMapAccess && $v instanceof ConstMapAccess) {
					$v = map_merge_recursive($ret[$k], $v);
				// or if both are arrays
				} else if(is_array($ret[$k]) && is_array($v)) {
					$v = array_merge_recursive($ret[$k], $v);
				// If they're both collections and both the same class, add one to the other
				} else if($ret[$k] instanceof Collection && $v instanceof Collection) {
					if(get_class($ret[$k]) == get_class($v)) {
						// We clone here so as to not screw up the original item
						$v = (clone $ret[$k])->addAll($v);
					}
				}
			}
			$ret[$k] = $v;
		}
	}

	return $ret;
}

/**
 * Vector equivalent of array_unshift
 */
function vector_unshift(MutableVector $v, mixed $val) : void {
	$v->add(null); // Add a null element to the end
	for ($i = $v->count()-1; $i > 0; $i--) {
		$v[$i] = $v[$i-1];
	}
	$v[0] = $val;
}
