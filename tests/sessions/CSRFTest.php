<?php

namespace beatbox\test;

use beatbox;

class CSRFTest extends beatbox\Test {
	/**
	 * @group fast
	 */
	public function testCSRFGeneration() {
		$a = [];
		for($i = 0; $i < 10; ++$i) {
			$a[] = generate_random_token();
		}

		for($i = 0; $i < 9; ++$i) {
			for($j = $i+1; $j < 10; ++$j) {
				$this->assertNotEquals($a[$i], $a[$j]);
			}
		}
	}

	/**
	 * @group fast
	 */
	public function testCSRFTest() {
		$existingToken = get_csrf_token();
		$differentToken = generate_random_token();

		$this->assertTrue(check_csrf_token($existingToken));
		$this->assertFalse(check_csrf_token($differentToken));

		$this->assertFalse(check_csrf_token(''));
		$this->assertFalse(check_csrf_token($existingToken . ' '));
		$this->assertFalse(check_csrf_token($existingToken[0]));
	}
}
