<?hh

namespace beatbox\test\constraint;
use HH\Traversable;

/**
 * Constraint for iterators/traversables to check to see if they
 * produce the correct sequence.
 */
class Produce extends \PHPUnit_Framework_Constraint {

	private $expected;
	private $overrun = false;
	private $underrun = false;

	/**
	 * $expected is an array of the expected values from
	 * the iterator.
	 * $overrun controls whether the tested iterator is allowed to
	 * overrun the expected array.
	 * $underrun control whether the tested iterator is allowed to
	 * stop before all elements in the expected array have been checked
	 */
	public function __construct(array $expected, \bool $overrun=false, \bool $underrun=false) {
		$this->expected = $expected;
		$this->overrun = $overrun;
		$this->underrun = $underrun;
	}

	public function evaluate(Traversable $other, \string $description = '', \bool $returnResult = false) : \bool {
		$success = false;

		$comp_factory = \PHPUnit_Framework_ComparatorFactory::getDefaultInstance();
		$index = 0;
		$exp_n = count($this->expected);

		try {
			foreach ($other as $value) {
				if ($index >= $exp_n) {
					if ($this->overrun) break;
					throw new \PHPUnit_Framework_ExpectationFailedException(
						trim($description."\nTraversable overran $exp_n expected values\nexpected: $exp_n"),
						null
					);
				}
				$expected = $this->expected[$index];
				$comparator = $comp_factory->getComparatorFor($expected, $value);
				$comparator->assertEquals($expected, $value);
				$index++;
			}
			if ($index < $exp_n && !$this->underrun) {
				throw new \PHPUnit_Framework_ExpectationFailedException(
					trim($description."\nTraversable produced fewer values than expected\nexpected: $exp_n, actual: $index"),
					null
				);
			}
		} catch (\PHPUnit_Framework_ComparisonFailure $e) {
			if ($returnResult) {
				return false;
			}

			throw new \PHPUnit_Framework_ExpectationFailedException(
				trim($description."\nDifferent at position $index\n".$e->getMessage()),
				$e
			);
		}

		return true;

	}

	protected function failureDescription(\mixed $other) : \string {
		return 'traversable produces expected values';
	}

	public function toString() : \string {
		return 'traversable produces expected values';
	}
}
