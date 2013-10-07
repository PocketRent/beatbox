<?php

namespace pr\base;

use \pr\base\test;
use Map;

class Test extends \PHPUnit_Framework_TestCase {
	public function assertMapsEqual(Map $expected, Map $actual, \string $message = null) {
		$aa = $actual->copyAsArray();
		$ea = $expected->copyAsArray();
		ksort($aa);
		ksort($ea);

		return $this->assertEquals($ea, $aa, $message);
	}

	public static function assertEquals($expected, $actual, $message='', $delta=0, $maxDepth=10, $canonicalize=FALSE, $ignoreCase=FALSE) {
		if (is_object($expected) && method_exists($expected, 'cmp')) {
			$constraint = new test\constraint\Compare($expected, 0);
			self::assertThat($actual, $constraint, $message);
		} else {
			parent::assertEquals($expected, $actual, $message, $delta, $maxDepth, $canonicalize, $ignoreCase);
		}
	}

	public static function assertProduces($expected, $actual, $message='', $overrun=false, $underrun=false) {
		$constraint = new test\constraint\Produce($expected, $overrun, $underrun);
		self::assertThat($actual, $constraint, $message);
	}

	public static function assertSetsEquals($expected, $actual, $message='', $delta=0, $maxDepth=10, $canonicalize=FALSE, $ignoreCase=FALSE) {
		if(is_object($expected)) {
			$e = [];
			foreach($expected as $v) $e[] = $v;
			$expected = $e;
		} else {
			$expected = (array)$expected;
		}
		if(is_object($actual)) {
			$e = [];
			foreach($actual as $v) $e[] = $v;
			$actual = $e;
		} else {
			$actual = (array)$actual;
		}

		// We serialize so that we can provide a consistent sort

		$expected = array_map('serialize', $expected);
		$actual = array_map('serialize', $actual);

		sort($expected);
		sort($actual);

		self::assertEquals($expected, $actual, $message, $delta, $maxDepth, $canonicalize, $ignoreCase);
	}
}
