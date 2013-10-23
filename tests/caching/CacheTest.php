<?hh

namespace beatbox\test;

use beatbox, beatbox\Cache;

class CacheTest extends beatbox\Test {

	/**
	 * @group fast
	 */
	public function testCheckEmpty() {
		$this->assertFalse(Cache::test("mykey"));
	}

	/**
	 * @group fast
	 */
	public function testSetKeys() {
		Cache::set("key1", 1);
		Cache::set("key2", 2);

		$this->assertEquals(1, Cache::get("key1"));
		$this->assertEquals(2, Cache::get("key2"));
	}

	/**
	 * @group fast
	 * @depends testSetKeys
	 */
	public function testGetOrSet() {
		$val = Cache::get_or_set("key1", function () { return 9; });
		$this->assertEquals(1, $val);

		$val = Cache::get_or_set("key3", function () { return 3; });
		$this->assertEquals(3, $val);
	}

	/**
	 * @group fast
	 * @depends testSetKeys
	 */
	public function testRemove() {
		$val = Cache::remove("key2");
		$this->assertFalse(Cache::test("key2"));
	}

	/**
	 * @group fast
	 */
	public function testAddTags() {
		Cache::set("tagged1", 'a', 0, ["tag1", "tag2"]);
		Cache::set("tagged2", 'a', 0, ["tag1", "tag3"]);
		Cache::set("tagged3", 'a', 0, ["tag4", "tag5"]);

		$this->assertTrue(Cache::test("tagged1"));
		$this->assertTrue(Cache::test("tagged2"));
		$this->assertTrue(Cache::test("tagged3"));
	}

	/**
	 * @group fast
	 * @depends testAddTags
	 */
	public function testDeleteTag() {
		Cache::delete_tags('tag2');
		$this->assertFalse(Cache::test('tagged1'));
		Cache::delete_tags('tag1','tag4');
		$this->assertFalse(Cache::test('tagged2'));
		$this->assertFalse(Cache::test('tagged3'));
	}
}
