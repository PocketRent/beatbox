<?hh

use beatbox\Browser;

/**
 * Checks if this site is in dev mode or not
 */
function in_dev() : bool {
	return (bool)DEV_MODE;
}

/**
 * Checks if this site is in live mode or not
 */
function in_live() : bool {
	return !in_dev();
}

/**
 * Checks if this request was made via AJAX
 */
function is_ajax() : bool {
	return
		(isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
		strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'xmlhttprequest') == 0) ||
		(in_dev() && isset($_REQUEST['ajax']));
}

/**
 * Checks to see if this request was made as a pagelet
 */
function is_pagelet() : bool {
	return
		isset($_SERVER['HTTP_X_PAGELET_FRAGMENT']) ||
		(in_dev() && isset($_REQUEST['pagelet']));
}

/**
 * Checks if this request was made via the CLI
 */
function is_cli() : bool {
	return php_sapi_name() == 'cli';
}

/**
 * Returns the type of the current device
 */
function device_type() : int {
	return Browser::device_type();
}

/**
 * Returns the request method
 */
function request_method() : string {
	return isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
}

/**
 * Was the request made using GET?
 */
function is_get() : bool {
	return request_method() == 'GET';
}

/**
 * Was the request made using POST?
 */
function is_post() : bool {
	return request_method() == 'POST';
}

/**
 * Was the request made using HEAD?
 */
function is_head() : bool {
	return request_method() == 'HEAD';
}

/**
 * Was the request made using PUT?
 */
function is_put() : bool {
	return request_method() == 'PUT';
}

/**
 * Was the request made using DELETE?
 */
function is_delete() : bool {
	return request_method() == 'DELETE';
}

/**
 * Was the request made using PATCH?
 */
function is_patch() : bool {
	return request_method() == 'PATCH';
}

/**
 * Does the browser support SVG?
 */
function have_svg() : bool {
	return Browser::svg();
}

/**
 * Does the browser support inline SVG?
 */
function have_inline_svg() : bool {
	return Browser::inline_svg();
}

/**
 * Get the value of a cookie
 */
function get_cookie(string $name): ?string {
	if (isset($_COOKIE[$name])) {
		return $_COOKIE[$name];
	}
	return null;
}

/**
 * Set a cookie's value.
 *
 * Has the same signature as setcookie() and updates the $_COOKIE super global
 */
function set_cookie(string $name, ?string $value = '', int $expire = 0, string $path = '',
					?string $domain = null, bool $secure = false, bool $httponly = false): bool {
	if (setcookie($name, $value, $expire, $path, $domain, $secure, $httponly)) {
		if ($expire > 0 && $expire < time()) {
			unset($_COOKIE[$name]);
		} else {
			$_COOKIE[$name] = $value;
		}
		return true;
	}
	return false;
}

/**
 * Get a value from the REQUEST superglobal
 */
function request_var(string $name): mixed {
	if (isset($_REQUEST[$name])) {
		return $_REQUEST[$name];
	}
	return null;
}

/**
 * Get a value from the GET superglobal
 */
function get_var(string $name): mixed {
	if (isset($_GET[$name])) {
		return $_GET[$name];
	}
	return null;
}

/**
 * Get a value from the POST superglobal
 */
function post_var(string $name): mixed {
	if (isset($_POST[$name])) {
		return $_POST[$name];
	}
	return null;
}

/**
 * Get a value from the FILES superglobal
 */
function files_var(string $name): ?array<string, mixed> {
	if (isset($_FILES[$name])) {
		return $_FILES[$name];
	}
	return null;
}

/**
 * Get a value from the SERVER superglobal
 */
function server_var(string $name): mixed {
	if (isset($_SERVER[$name])) {
		return $_SERVER[$name];
	}
	return null;
}

function inited() : bool {
	return isset($GLOBALS['<__inited>']) && $GLOBALS['<__inited>'];
}
