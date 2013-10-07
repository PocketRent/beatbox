<?php

/**
 * Get the item out of the session for the given key
 *
 * @uses pr\base\Session::get()
 */
function session_get($key) {
	return pr\base\Session::get($key);
}

/**
 * Check if there is a value set for the key in the session
 *
 * @uses pr\base\Session::isset()
 */
function session_exists($key) {
	return pr\base\Session::exists($key);
}

/**
 * Set the session value for the given key
 *
 * @uses pr\base\Session::set()
 */
function session_set($key, $value) {
	return pr\base\Session::set($key, $value);
}

/**
 * Clear the value from the session for the given key
 *
 * @uses pr\base\Session::clear()
 */
function session_clear($key) {
	return pr\base\Session::clear($key);
}
