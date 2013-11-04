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
	public function testVectorUnshift() {
		$vector = \Vector { 1, 2, 3 };
		vector_unshift($vector, 0);

		$this->assertEquals(\Vector { 0, 1, 2, 3 }, $vector);
	}

	/**
	 * @group sanity
	 */
	public function testCheckToken() {
		$this->assertTrue(check_token("abcde", "abcde"));
	}

}
