<?php

use pr\base\Browser;

/**
 * Checks if this site is in dev mode or not
 */
function in_dev() {
	return (bool)DEV_MODE;
}

/**
 * Checks if this site is in live mode or not
 */
function in_live() {
	return !in_dev();
}

/**
 * Checks if this request was made via AJAX
 */
function is_ajax() {
	return
		(isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
		strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'xmlhttprequest') == 0) ||
		(in_dev() && isset($_REQUEST['ajax']));
}

/**
 * Checks to see if this request was made as a pagelet
 */
function is_pagelet() {
	return
		isset($_SERVER['HTTP_X_PAGELET_FRAGMENT']) ||
		(in_dev() && isset($_REQUEST['pagelet']));
}

/**
 * Checks if this request was made via the CLI
 */
function is_cli() {
	return php_sapi_name() == 'cli';
}

/**
 * Returns the type of the current device
 */
function device_type() {
	return Browser::device_type();
}

/**
 * Returns the request method
 */
function request_method() {
	return isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
}

/**
 * Was the request made using GET?
 */
function is_get() {
	return request_method() == 'GET';
}

/**
 * Was the request made using POST?
 */
function is_post() {
	return request_method() == 'POST';
}

/**
 * Was the request made using HEAD?
 */
function is_head() {
	return request_method() == 'HEAD';
}

/**
 * Was the request made using PUT?
 */
function is_put() {
	return request_method() == 'PUT';
}

/**
 * Was the request made using DELETE?
 */
function is_delete() {
	return request_method() == 'DELETE';
}

/**
 * Was the request made using PATCH?
 */
function is_patch() {
	return request_method() == 'PATCH';
}

function have_svg() {
	return Browser::svg();
}

function have_inline_svg() {
	return Browser::inline_svg();
}
