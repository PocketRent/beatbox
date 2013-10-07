<?php

namespace beatbox\test;

use beatbox, beatbox\orm\DateTimeType;

class DateTimeTest extends beatbox\Test {
	private $old_tz;
	public function setUp() {
		$this->old_tz = date_default_timezone_get();
		date_default_timezone_set('UTC');
	}

	public function tearDown() {
		date_default_timezone_set($this->old_tz);
	}

	public function testEqual() {
		$date_type1 = new DateTimeType("2000-01-01 00:00:00");
		$date_type2 = new DateTimeType("2000-01-01 00:00:00");

		$this->assertTrue($date_type1->eq($date_type2));

		$date = new \DateTime("2000-01-02 00:00:00");
		$this->assertFalse($date_type1->eq($date));
	}

	public function testCompare() {
		$date_type1 = new DateTimeType("2000-01-01 00:00:00");
		$date_type2 = new DateTimeType("2000-01-02 00:00:00");
		$this->assertTrue($date_type1->lt($date_type2));

		$date = new \DateTime("2000-01-01 00:00:00");
		$this->assertFalse($date_type2->lt($date));
	}

	/**
	 * @group fast
	 */
	public function testToString() {
		$date = new \DateTime("2000-01-01 00:00:00");
		$date_t = new DateTimeType("2000-01-01 00:00:00");

		$this->assertEquals($date->format('Y-m-d H:i:s e'), $date_t->__toString());
	}

	/**
	 * @group fast
	 * @depends testToString
	 */
	public function testTimezone() {
		$date = new DateTimeType("2000-01-01 00:00:00");
		$date->setTimezone('Pacific/Auckland');
		$this->assertEquals("2000-01-01 13:00:00 Pacific/Auckland", $date->__toString());

		$date = new DateTimeType("2000-01-01 00:00:00+12");
		$this->assertEquals("2000-01-01 00:00:00 UTC", $date->__toString());
	}

	/**
	 * @group fast
	 */
	public function testParts() {
		$date = new DateTimeType("2000-01-02");
		$this->assertEquals("01-02-2000", $date->format('m-d-Y'));

		$date = new DateTimeType("11:12:13");
		$this->assertEquals("13:11:12", $date->format('s:H:i'));
	}

}
