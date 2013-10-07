<?php

namespace pr\test;

use pr\base;

class MiscTest extends base\Test {
	/**
	 * @group sanity
	 */
	public function testHostDomain() {
		$this->assertNull(host_domain());

		$_SERVER['HTTP_HOST'] = 'localhost';
		$this->assertEquals(host_domain(), 'localhost');

		$_SERVER['HTTP_HOST'] = 'localhost:8080';
		$this->assertEquals(host_domain(), 'localhost');
	}
}
