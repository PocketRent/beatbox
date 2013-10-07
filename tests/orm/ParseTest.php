<?php

namespace beatbox\test;

use \beatbox, Vector;

class ParseTest extends beatbox\Test {

	/**
	 * @group sanity
	 */
	public function testArrayEmpty() {
		$this->assertEquals(Vector {}, db_parse_array(",", ""));
	}

	/**
	 * @group sanity
	 * @depends testArrayEmpty
	 */
	public function testArrayOne() {
		$this->assertEquals(Vector { "1" }, db_parse_array(',', "1"));
	}

	/**
	 * @group fast
	 * @depends testArrayEmpty
	 */
	public function testArraySimple() {
		$v = db_parse_array(",", '"abc","123","xyz"');
		$this->assertEquals(Vector { '"abc"', '"123"', '"xyz"' }, $v);
	}

	/**
	 * @group fast
	 * @depends testArrayEmpty
	 */
	public function testArrayEscape() {
		$v = db_parse_array(",", '"abc\\"","123",5');
		$this->assertEquals(Vector { '"abc\\""', '"123"', '5' }, $v);
	}

	/**
	 * @group fast
	 * @depends testArrayEmpty
	 */
	public function testArrayNestedDelimeters() {
		$v = db_parse_array(",", '"abc,123", "xyz,ijk"');
		$this->assertEquals(Vector { '"abc,123"', '"xyz,ijk"' }, $v);
	}

	/**
	 * @group fast
	 * @depends testArrayEmpty
	 */
	public function testArrayNestedArray() {
		$v = db_parse_array(",", '{1,2},{3,4}');
		$this->assertEquals(Vector { '{1,2}', '{3,4}' }, $v);
	}

	/**
	 * @depends testArrayNestedArray
	 */
	public function testArrayComplex() {
		$v = db_parse_array(',','{"12{3\",\'",55},{abc,xyz}');
		$this->assertEquals(Vector { '{"12{3\",\'",55}', '{abc,xyz}' }, $v);
	}

	/**
	 * @group sanity
	 */
	public function testStringEmpty() {
		$this->assertEquals("", db_parse_string(""));
	}

	/**
	 * @group sanity
	 */
	public function testStringSimple() {
		$string = "Hello my name is";
		$this->assertEquals($string, db_parse_string($string));
	}

	/**
	 * @group fast
	 */
	public function testStringNoEscape() {
		$string = "Hello my name is";
		$this->assertEquals($string, db_parse_string("\"$string\""));
	}

	/**
	 * @group fast
	 */
	public function testStringEscape() {
		$this->assertEquals("Hi \"there\\", db_parse_string('"Hi \\"there\\\\"'));
		$this->assertEquals("Hi\"there", db_parse_string('"Hi""there"'));
	}

	/**
	 * @group sanity
	 */
	public function testCompositeEmpty() {
		$this->assertEquals(Vector {}, db_parse_composite(""));
		$this->assertEquals(Vector {}, db_parse_composite("()"));
	}

	/**
	 * @group sanity
	 */
	public function testCompositeOne() {
		$this->assertEquals(Vector { "abc" }, db_parse_composite("(abc)"));
	}

	/**
	 * @group fast
	 */
	public function testCompositeSimple() {
		$this->assertEquals(Vector { "abc", '1', 't' }, db_parse_composite('(abc,1,t)'));
	}

	/**
	 * @group fast
	 */
	public function testCompositeNested() {
		$this->assertEquals(Vector {"(abc)", "123"}, db_parse_composite('((abc),123)'));
	}

	/**
	 * @group fast
	 */
	public function testCompositeString() {
		$this->assertEquals(Vector {'123"456'}, db_parse_composite('("123""456")'));
	}

	public function testCompositeNestedString() {
		$this->assertEquals(Vector {'("123""456")', '1'}, db_parse_composite('("(""123""""456"")",1)'));
	}
}
