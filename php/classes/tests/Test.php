<?hh

namespace beatbox;

use \beatbox\test;
use Map;

class Test extends \PHPUnit_Framework_TestCase {
	public function assertMapsEqual(Map $expected, Map $actual, \string $message = null) : \void {
		$aa = $actual->toArray();
		$ea = $expected->toArray();
		ksort($aa);
		ksort($ea);

		return $this->assertEquals($ea, $aa, $message);
	}

	public static function assertEquals(\mixed $expected, \mixed $actual, \string $message='', \int $delta=0, \int $maxDepth=10, \bool $canonicalize=FALSE, \bool $ignoreCase=FALSE) : \void {
		if (is_object($expected) && method_exists($expected, 'cmp')) {
			$constraint = new test\constraint\Compare($expected, 0);
			self::assertThat($actual, $constraint, $message);
		} else {
			parent::assertEquals($expected, $actual, $message, $delta, $maxDepth, $canonicalize, $ignoreCase);
		}
	}

	public static function assertProduces(\mixed $expected, \mixed $actual, \string $message='', \bool $overrun=false, \bool $underrun=false) : \void {
		$constraint = new test\constraint\Produce($expected, $overrun, $underrun);
		self::assertThat($actual, $constraint, $message);
	}

	public static function assertSetsEquals(\mixed $expected, \mixed $actual, \string $message='', \int $delta=0, \int $maxDepth=10, \bool $canonicalize=FALSE, \bool $ignoreCase=FALSE) : \void {
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
