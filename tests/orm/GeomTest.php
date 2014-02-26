<?hh

namespace beatbox\test;

use beatbox;
use beatbox\orm\geom;

class GeomTest extends beatbox\Test {

	/**
	 * @group sanity
	 */
	public function testParsePoint() {
		$point = geom\point::fromString("(1,2)");
		$this->assertEquals($point, new geom\point(1.0, 2.0));

		$point = geom\point::fromString("5,6");
		$this->assertEquals($point, new geom\point(5.0, 6.0));
	}

	/**
	 * @group sanity
	 */
	public function testParseLseg() {
		$expected = new geom\lseg(
			new geom\point(1.0,2.0), new geom\point(3.0,4.0)
		);
		$lseg = geom\lseg::fromString("[ (1,2),(3,4)]");
		$this->assertEquals($expected, $lseg);

		$lseg = geom\lseg::fromString("( ( 1,2),(3,4))");
		$this->assertEquals($expected, $lseg);

		$lseg = geom\lseg::fromString("(1,2),(3,4)");
		$this->assertEquals($expected, $lseg);

		$lseg = geom\lseg::fromString("1,2,3,4");
		$this->assertEquals($expected, $lseg);
	}

	/**
	 * @group sanity
	 */
	public function testParseBox() {
		$expected = new geom\box(
			new geom\point(1.0,2.0), new geom\point(3.0,4.0)
		);

		$lseg = geom\box::fromString("((1,2),(3,4))");
		$this->assertEquals($expected, $lseg);

		$lseg = geom\box::fromString("(1,2),(3,4)");
		$this->assertEquals($expected, $lseg);

		$lseg = geom\box::fromString("1,2, 3,4");
		$this->assertEquals($expected, $lseg);
	}

	/**
	 * @group sanity
	 */
	public function testParsePath() {
		$expected_points = FixedVector {
			new geom\point(1.0, 2.0),
			new geom\point(2.0, 4.0),
			new geom\point(3.0, 1.0)
		};

		$path = geom\path::fromString("[(1,2),(2,4),(3,1)]");
		$this->assertTrue($path->open);
		$this->assertEquals($expected_points, $path->points);

		$path = geom\path::fromString("((1,2),(2,4),(3,1))");
		$this->assertFalse($path->open);
		$this->assertEquals($expected_points, $path->points);

		$path = geom\path::fromString("(1,2),(2,4),(3,1)");
		$this->assertFalse($path->open);
		$this->assertEquals($expected_points, $path->points);

		$path = geom\path::fromString("(1,2,2,4,3,1)");
		$this->assertFalse($path->open);
		$this->assertEquals($expected_points, $path->points);

		$path = geom\path::fromString("1,2,2,4,3,1");
		$this->assertFalse($path->open);
		$this->assertEquals($expected_points, $path->points);

		$path = geom\path::fromString("1,2");
		$this->assertFalse($path->open);
		$this->assertEquals(FixedVector {new geom\point(1.0, 2.0)}, $path->points);
	}

	/**
	 * @group sanity
	 */
	public function testParsePoly() {
		$expected_points = FixedVector {
			new geom\point(1.0, 2.0),
			new geom\point(2.0, 4.0),
			new geom\point(3.0, 1.0)
		};

		$poly = geom\polygon::fromString("((1,2),(2,4),(3,1))");
		$this->assertEquals($expected_points, $poly->points);

		$poly = geom\polygon::fromString("(1,2),(2,4),(3,1)");
		$this->assertEquals($expected_points, $poly->points);

		$poly = geom\polygon::fromString("(1,2,2,4,3,1)");
		$this->assertEquals($expected_points, $poly->points);

		$poly = geom\polygon::fromString("1,2,2,4,3,1");
		$this->assertEquals($expected_points, $poly->points);

		$poly = geom\polygon::fromString("1,2");
		$this->assertEquals(FixedVector {new geom\point(1.0, 2.0)}, $poly->points);
	}

	/**
	 * @group sanity
	 */
	public function testParseCircle() {
		$expected = new geom\circle(new geom\point(), 5.0);

		$circle = geom\circle::fromString('<(0,0),5>');
		$this->assertEquals($expected, $circle);

		$circle = geom\circle::fromString('((0,0),5)');
		$this->assertEquals($expected, $circle);

		$circle = geom\circle::fromString('(0,0),5');
		$this->assertEquals($expected, $circle);

		$circle = geom\circle::fromString('0,0,5');
		$this->assertEquals($expected, $circle);
	}

	/**
	 * @expectedException beatbox\orm\TypeParseException
	 */
	public function testParsePointFail1() {
		geom\point::fromString("(1 2)");
	}
	/**
	 * @expectedException beatbox\orm\TypeParseException
	 */
	public function testParsePointFail2() {
		geom\point::fromString("(1, 2");
	}
	/**
	 * @expectedException beatbox\orm\TypeParseException
	 */
	public function testParsePointFail3() {
		geom\point::fromString("(q, 2");
	}

}
