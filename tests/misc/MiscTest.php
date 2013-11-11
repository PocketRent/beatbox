<?hh

namespace beatbox\test;

use beatbox;

class MiscTest extends beatbox\Test {
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

	/**
	 * @group sanity
	 */
	public function testCheckToken() {
		$this->assertTrue(check_token("abcde", "abcde"));
	}
}
