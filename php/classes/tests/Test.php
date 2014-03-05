<?php

namespace beatbox;

use \beatbox\test;

class Test extends \PHPUnit_Framework_TestCase {
	public function assertMapsEqual(Map $expected, Map $actual, $message = '') {
		$aa = $actual->toArray();
		$ea = $expected->toArray();
		ksort($aa);
		ksort($ea);

		return $this->assertEquals($ea, $aa, $message);
	}

	public static function assertEquals(\mixed $expected, \mixed $actual, \string $message='',
										\int $delta=0, \int $maxDepth=10, \bool $canonicalize=FALSE,
										\bool $ignoreCase=FALSE) : \void {
		if ($expected instanceof Comparable) {
			$constraint = new test\constraint\Compare($expected, 0);
			self::assertThat($actual, $constraint, $message);
		} else {
			parent::assertEquals($expected, $actual, $message, $delta, $maxDepth, $canonicalize,
									$ignoreCase);
		}
	}

	public function assertProduces(array $expected, \mixed $actual, \string $message='',
											\bool $overrun=false, \bool $underrun=false) : \void {
		$constraint = new test\constraint\Produce($expected, $overrun, $underrun);
		self::assertThat($actual, $constraint, $message);
	}

	public function assertSetsEquals(\mixed $expected, \mixed $actual, \string $message='',
											\int $delta=0, \int $maxDepth=10,
											\bool $canonicalize=FALSE,
											\bool $ignoreCase=FALSE) : \void {
		if($expected instanceof Traversable) {
			$e = [];
			foreach($expected as $v) $e[] = $v;
			$expected = $e;
		} else {
			$expected = (array)$expected;
		}
		if($actual instanceof Traversable) {
			$e = [];
			foreach($actual as $v) $e[] = $v;
			$actual = $e;
		} else {
			$actual = (array)$actual;
		}

		// We serialize so that we can provide a consistent sort

		$expected = array_map(fun('serialize'), $expected);
		$actual = array_map(fun('serialize'), $actual);

		sort($expected);
		sort($actual);

		$this->assertEquals($expected, $actual, $message, $delta, $maxDepth, $canonicalize,
							$ignoreCase);
	}
}
