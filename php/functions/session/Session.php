<?hh

/**
 * Get the item out of the session for the given key
 *
 * @uses beatbox\Session::get()
 */
function session_get(string $key) : mixed {
	return beatbox\Session::get($key);
}

/**
 * Check if there is a value set for the key in the session
 *
 * @uses beatbox\Session::isset()
 */
function session_exists(string $key) : bool {
	return beatbox\Session::exists($key);
}

/**
 * Set the session value for the given key
 *
 * @uses beatbox\Session::set()
 */
function session_set(string $key, mixed $value) : void {
	return beatbox\Session::set($key, $value);
}

/**
 * Clear the value from the session for the given key
 *
 * @uses beatbox\Session::clear()
 */
function session_clear(string $key) : void {
	return beatbox\Session::clear($key);
}
