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

function have_svg() : bool {
	return Browser::svg();
}

function have_inline_svg() : bool {
	return Browser::inline_svg();
}
