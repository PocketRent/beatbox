<?hh

/**
 * Get the item out of the session for the given key
 *
 * @uses beatbox\Session::get()
 */
function session_get($key) {
	return beatbox\Session::get($key);
}

/**
 * Check if there is a value set for the key in the session
 *
 * @uses beatbox\Session::isset()
 */
function session_exists($key) {
	return beatbox\Session::exists($key);
}

/**
 * Set the session value for the given key
 *
 * @uses beatbox\Session::set()
 */
function session_set($key, $value) {
	return beatbox\Session::set($key, $value);
}

/**
 * Clear the value from the session for the given key
 *
 * @uses beatbox\Session::clear()
 */
function session_clear($key) {
	return beatbox\Session::clear($key);
}
