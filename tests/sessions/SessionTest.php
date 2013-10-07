<?php

namespace pr\test;

use pr\base, pr\base\Session;

class SessionTest extends base\Test {
	use base\test\Redis { 
		tearDown as rCleanUp;
	}

	protected function tearDown() {
		Session::reset();
		// Remove all test_ keys
		$this->rCleanUp();
	}

	/**
	 * @group fast
	 */
	public function testGetSet() {
		Session::set('test', 'value');
		$this->assertSame('value', session_get('test'));

		session_set('test', $val = \Vector {});
		$this->assertSame($val, Session::get('test'));
	}

	/**
	 * @group fast
	 * @depends testGetSet
	 */
	public function testExists() {
		$this->assertFalse(session_exists('test'));
		session_set('test', 'value');
		$this->assertTrue(session_exists('test'));
		session_clear('test');
		$this->assertFalse(session_exists('test'));
	}

	/**
	 * @group fast
	 * @depends testGetSet
	 */
	public function testEnd() {
		Session::set('test', 'value');
		Session::end();
		$this->assertSame('value', Session::get('test'));
	}

	/**
	 * @group fast
	 * @depends testGetSet
	 */
	public function testCSRF() {
		$this->assertNotNull($token = Session::get('CSRF'), 'Should always have a CSRF token');
		Session::end();
		$this->assertEquals($token, Session::get('CSRF'), 'Restarting session should maintain the token.');

		try {
			Session::set('CSRF', 2);
			$this->fail('Should not be able to override CSRF value');
		} catch(\InvalidArgumentException $e) {
			
		}
	}

	/**
	 * @group fast
	 * @depends testEnd
	 */
	public function testRemove() {
		Session::set('test', 'value');
		Session::set('o', 'b');
		Session::end();

		$this->assertSame('value', Session::get('test'));
		$this->assertSame('b', Session::get('o'));
		Session::clear('o');
		Session::clear('test');
		Session::end();
		
		$this->assertFalse(Session::exists('test'));
		$this->assertFalse(Session::exists('o'));
	}
}
