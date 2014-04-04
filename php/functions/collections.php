<?hh // strict

/**
 * Recursively merge one or more maps.
 *
 * Behaves in a similar manner to array_merge_recusrive
 */
function map_merge_recursive<Tk>(ConstMap<Tk,mixed> $base, ...) : Map<Tk,mixed> {
	$ret = new Map();

	$ret->setAll($base);

	$args = func_get_args();
	array_shift($args);

	foreach($args as $extra) {
		invariant($extra instanceof ConstMap,
					__FUNCTION__ . ' passed an argument that does not implement ConstMapAccess');
		foreach($extra as $k => $v) {
			if($ret->contains($k)) {
				$merge_val = $ret[$k];
				// Recurse if both sides are maps
				if($merge_val instanceof ConstMap && $v instanceof ConstMap) {
					$v = map_merge_recursive($merge_val, $v);
				// or if both are arrays
				} else if(is_array($ret[$k]) && is_array($v)) {
					$v = array_merge_recursive($ret[$k], $v);
				// If they're both collections and both the same class, add one to the other
				} else if($merge_val instanceof Collection && $v instanceof Collection) {
					if(get_class($merge_val) == get_class($v)) {
						// We clone here so as to not screw up the original item
						$v = (clone $merge_val)->addAll($v);
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
function vector_unshift<T>(MutableVector<T> $v, T $val) : void {
	// UNSAFE - because of the temporary null added to the beginning
	$v->add(null); // Add a null element to the end
	for ($i = $v->count()-1; $i > 0; $i--) {
		$v[$i] = $v[$i-1];
	}
	$v[0] = $val;
}
