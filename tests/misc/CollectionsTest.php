<?hh

namespace beatbox\test;

use beatbox, Map, Set, Vector;

class CollectionsText extends beatbox\Test {
	/**
	 * @group sanity
	 */
	public function testVectorUnshift() {
		$vector = Vector { 1, 2, 3 };
		vector_unshift($vector, 0);

		$this->assertEquals(Vector { 0, 1, 2, 3 }, $vector);
	}

	/**
	 * @group fast
	 */
	public function testSimpleMapMergeRecursive() {
		$first = Map {1 => 2, 3 => 4};

		$this->assertMapsEqual($first, map_merge_recursive($first));

		$second = Map {2 => 1, 4 => 3};

		$this->assertMapsEqual(Map {1 => 2, 2 => 1, 3 => 4, 4 => 3}, map_merge_recursive($first, $second));

		$third = Map {5 => 5};

		$this->assertMapsEqual(Map {1 => 2, 2 => 1, 3 => 4, 4 => 3, 5 => 5}, map_merge_recursive($first, $second, $third));

		$fourth = Map {1 => 1};

		$this->assertMapsEqual(Map {1 => 1, 3 => 4}, map_merge_recursive($first, $fourth));
		$this->assertMapsEqual(Map {1 => 2, 3 => 4}, map_merge_recursive($fourth, $first));
	}

	/**
	 * @group fast
	 */
	public function testNestedMapMergeRecursive() {
		$first = Map {
			1 => Map {
				2 => 3,
				4 => 5
			},
			2 => 'Hello',
		};
		$second = Map {
			1 => Map {
				2 => 2,
				3 => 3,
			},
			2 => 'World',
			3 => 1
		};

		$this->assertMapsEqual(Map {
			1 => Map {
				2 => 2,
				3 => 3,
				4 => 5
			},
			2 => 'World',
			3 => 1
		}, map_merge_recursive($first, $second));

		$third = Map {
			2 => Map {
				1 => 1
			}
		};

		$this->assertMapsEqual(Map {
			1 => Map {
				2 => 2,
				3 => 3,
				4 => 5
			},
			2 => Map {
				1 => 1
			},
			3 => 1
		}, map_merge_recursive($first, $second, $third));

		$fourth = Map {
			2 => Map {
				1 => 2,
				2 => 1
			},
			1 => 1
		};

		$this->assertMapsEqual(Map {
			1 => 1,
			2 => Map {
				1 => 2,
				2 => 1
			},
			3 => 1
		}, map_merge_recursive($first, $second, $third, $fourth));
	}

	/**
	 * @group fast
	 */
	public function testMapMergeRecursiveWithCollections() {
		$set1 = Map {
			1 => Set {
				1, 2, 3
			}
		};
		$set2 = Map {
			1 => Set {
				3, 4, 5
			}
		};
		$vector1 = Map {
			1 => Vector {
				1, 2, 3
			}
		};
		$vector2 = Map {
			1 => Vector {
				3, 4, 5
			}
		};

		$this->assertMapsEqual(Map {
			1 => Set {
				1, 2, 3, 4, 5
			}
		}, map_merge_recursive($set1, $set2));
		$this->assertMapsEqual(Map {
			1 => Set {
				1, 2, 3, 4, 5
			}
		}, map_merge_recursive($set2, $set1));

		$this->assertMapsEqual(Map {
			1 => Vector {
				1, 2, 3, 3, 4, 5
			}
		}, map_merge_recursive($vector1, $vector2));
		$this->assertMapsEqual(Map {
			1 => Vector {
				3, 4, 5, 1, 2, 3
			}
		}, map_merge_recursive($vector2, $vector1));

		$this->assertMapsEqual(Map {
			1 => Vector {
				1, 2, 3
			}
		}, map_merge_recursive($set1, $vector1));

		$this->assertMapsEqual(Map {
			1 => Set {
				1, 2, 3
			}
		}, map_merge_recursive($vector1, $set1));
	}
}
