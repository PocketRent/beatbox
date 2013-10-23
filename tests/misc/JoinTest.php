<?hh

namespace beatbox\test;

use beatbox, Vector;

class JoinTest extends beatbox\Test {

	/**
	 * @group sanity
	 */
	public function testJoinEmpty() {
		$this->assertEquals(pr_join(',',[]), "");
	}

	/**
	 * @group sanity
	 * @depends testJoinEmpty
	 */
	public function testJoinOne() {
		$this->assertEquals(pr_join(',',[123]), "123");
	}

	/**
	 * @group fast
	 * @depends testJoinEmpty
	 */
	public function testJoinArray() {
		$this->assertEquals(pr_join(',',[123,456]), "123,456");
	}

	/**
	 * @group fast
	 * @depends testJoinEmpty
	 */
	public function testJoinIterator() {
		$v = Vector {"abc", "def"};
		$it = $v->getIterator();
		$this->assertEquals(pr_join(', ', $it), "abc, def");
	}

	/**
	 * @group fast
	 * @depends testJoinEmpty
	 */
	public function testJoinIterable() {
		$v = Vector {"abc", "def"};
		$this->assertEquals(pr_join(' => ', $v), "abc => def");
	}

	/**
	 * @group fast
	 * @depends testJoinEmpty
	 */
	public function testJoinGenerator() {
		$gen = function () {
			yield "abc";
			yield 123;
			yield 66.66;
		};
		$this->assertEquals(pr_join('.', $gen()), "abc.123.66.66");
	}
}
