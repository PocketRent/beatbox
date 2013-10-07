<?php

namespace pr\test;

use pr\base;

class EnvTest extends base\Test {
	/**
	 * @group sanity
	 */
	public function testInDev() {
		// in_dev should be a direct mapping to the DEV_MODE constant
		$this->assertSame(in_dev(), (bool)DEV_MODE);
		// in_live should be opposite to both in_dev and DEV_MODE
		$this->assertSame(in_live(), !DEV_MODE);
		$this->assertSame(in_live(), !in_dev());
		// Can't do more than this, because constants
	}

	/**
	 * @group sanity
	 */
	public function testIsCli() {
		// We assume that tests are always run from the CLI
		$this->assertTrue(is_cli());
	}

	/**
	 * @group sanity
	 */
	public function testIsAjax() {
		unset($_SERVER['HTTP_X_REQUESTED_WITH']);
		unset($_REQUEST['ajax']);
		$this->assertFalse(is_ajax());
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'Not Ajax';
		$this->assertFalse(is_ajax());
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHTTPRequest';
		$this->assertTrue(is_ajax());
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttpREQUEST';
		$this->assertTrue(is_ajax());
		unset($_SERVER['HTTP_X_REQUESTED_WITH']);

		$this->assertFalse(is_ajax());
		$_REQUEST['ajax'] = '';
		$this->assertTrue(is_ajax());
		$_REQUEST['ajax'] = '1';
		$this->assertTrue(is_ajax());
		unset($_REQUEST['ajax']);
	}

	/**
	 * @group sanity
	 */
	public function testDeviceType() {
		unset($_COOKIE['ci']);
		// Default is desktop
		$this->assertSame(DEVICE_DESKTOP, device_type());
	}

	/**
	 * @group sanity
	 */
	public function testRequestMethods() {
		unset($_SERVER['REQUEST_METHOD']);
		$this->assertEquals('GET', request_method());
		$this->assertTrue(is_get());
		$this->assertFalse(is_post());
		$this->assertFalse(is_head());
		$this->assertFalse(is_put());
		$this->assertFalse(is_delete());
		$this->assertFalse(is_patch());

		$_SERVER['REQUEST_METHOD'] = 'post';
		$this->assertEquals('POST', request_method());
		$this->assertFalse(is_get());
		$this->assertTrue(is_post());
		$this->assertFalse(is_head());
		$this->assertFalse(is_put());
		$this->assertFalse(is_delete());
		$this->assertFalse(is_patch());


		$_SERVER['REQUEST_METHOD'] = 'get';
		$this->assertEquals('GET', request_method());
		$this->assertTrue(is_get());
		$this->assertFalse(is_post());
		$this->assertFalse(is_head());
		$this->assertFalse(is_put());
		$this->assertFalse(is_delete());
		$this->assertFalse(is_patch());

		$_SERVER['REQUEST_METHOD'] = 'head';
		$this->assertEquals('HEAD', request_method());
		$this->assertFalse(is_get());
		$this->assertFalse(is_post());
		$this->assertTrue(is_head());
		$this->assertFalse(is_put());
		$this->assertFalse(is_delete());
		$this->assertFalse(is_patch());

		$_SERVER['REQUEST_METHOD'] = 'PUT';
		$this->assertEquals('PUT', request_method());
		$this->assertFalse(is_get());
		$this->assertFalse(is_post());
		$this->assertFalse(is_head());
		$this->assertTrue(is_put());
		$this->assertFalse(is_delete());
		$this->assertFalse(is_patch());

		$_SERVER['REQUEST_METHOD'] = 'DELETE';
		$this->assertEquals('DELETE', request_method());
		$this->assertFalse(is_get());
		$this->assertFalse(is_post());
		$this->assertFalse(is_head());
		$this->assertFalse(is_put());
		$this->assertTrue(is_delete());
		$this->assertFalse(is_patch());

		$_SERVER['REQUEST_METHOD'] = 'PATCH';
		$this->assertEquals('PATCH', request_method());
		$this->assertFalse(is_get());
		$this->assertFalse(is_post());
		$this->assertFalse(is_head());
		$this->assertFalse(is_put());
		$this->assertFalse(is_delete());
		$this->assertTrue(is_patch());

		$_SERVER['REQUEST_METHOD'] = 'TEST';
		$this->assertEquals('TEST', request_method());
		$this->assertFalse(is_get());
		$this->assertFalse(is_post());
		$this->assertFalse(is_head());
		$this->assertFalse(is_put());
		$this->assertFalse(is_delete());
		$this->assertFalse(is_patch());

	}
}
