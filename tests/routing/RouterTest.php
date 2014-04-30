<?hh

namespace beatbox\test;

use beatbox;

class RouterTest extends beatbox\Test {
	// Wipe the existing routes for each test
	public function setUp() {
		TestRouter::reset();
	}

	/**
	 * @group sanity
	 */
	public function testEmpty() {
		$this->assertNull(beatbox\Router::get_routes_for_path('/'));
		$this->assertNull(beatbox\Router::get_routes_for_path('home'));
	}

	/**
	 * @group sanity
	 * @depends testEmpty
	 */
	public function testAddRoute() {
		beatbox\Router::add_simple_routes(Map {
			'/' => Map { 'page' => fun('pageGenerator') }
		});
		beatbox\Router::add_routes(Map {
			'home' => Pair { Map {
				'form' => fun('homeForm'),
				'logout' => fun('homeLogout')
			}, Map {'CSRF' => true}}
		});

		$this->assertEquals(
			Pair { Map {'page' => 'pageGenerator'}, Map {}},
			beatbox\Router::get_routes_for_path('/')
		);

		$this->assertEquals(
			Pair { Map {'form' => 'homeForm', 'logout' => 'homeLogout'}, Map {'CSRF' => true}},
			beatbox\Router::get_routes_for_path('home')
		);

		beatbox\Router::add_routes(Map {
			'/' => Pair { Map {'form' => fun('pageForm')}, Map{'CSRF' => true}},
			'home' => Pair{Map {}, Map {'CSRF' => false}}
		});

		$this->assertEquals(
			Pair { Map {'page' => 'pageGenerator', 'form' => 'pageForm'}, Map {'CSRF' => true}},
			beatbox\Router::get_routes_for_path('/')
		);

		$this->assertEquals(
			Pair { Map {'form' => 'homeForm', 'logout' => 'homeLogout'}, Map {'CSRF' => false}},
			beatbox\Router::get_routes_for_path('home')
		);
	}

	/**
	 * @group fast
	 * @depends testAddRoute
	 */
	public function testReset() {
		beatbox\Router::add_simple_routes(Map {
			'/' => Map { 'page' => fun('pageGenerator') }
		});
		beatbox\Router::add_routes(Map {
			'home' => Pair { Map {
				'form' => fun('homeForm'),
				'logout' => fun('homeLogout')
			}, Map {'CSRF' => true}}
		});
		TestRouter::reset();
		$this->assertNull(beatbox\Router::get_routes_for_path('/'));
		$this->assertNull(beatbox\Router::get_routes_for_path('home'));
	}

	/**
	 * @group fast
	 * @depends testAddRoute
	 */
	public function testBaseRoute() {
		$args = [];
		$called = false;

		beatbox\Router::add_simple_routes(Map {
			'/' => Map {'page' => function(beatbox\Path $url, beatbox\Extension $ext,
											beatbox\Metadata $md) use(&$args, &$called) {
				$args = func_get_args();
				$called = true;
				return 'Hello';
			}}
		});

		$actual = beatbox\Router::route('/', Vector {'page'});

		$this->assertTrue($called, 'Was our callback called');
		$this->assertEquals([ImmVector {'/'}, null, Map {}], $args,
								'We should have a list of segments, no extension and no metadata');
		$this->assertEquals('Hello', $actual, 'Our callback correctly returned');

		// Test that overriding works properly
		beatbox\Router::add_simple_routes(Map {
			'/' => Map {'page' => function(beatbox\Path $url, beatbox\Extension $ext,
											beatbox\Metadata $md) use(&$args, &$called) {
				$args = array_reverse(func_get_args());
				$called = false;
				return 'Good bye';
			}}
		});

		$actual = beatbox\Router::route('/', Vector {'page'});

		$this->assertFalse($called, 'Was our callback called');
		$this->assertEquals([Map {}, null, ImmVector {'/'}], $args,
							'We should have a list of segments, no extension and no metadata');
		$this->assertEquals('Good bye', $actual, 'Our callback correctly returned');
	}

	/**
	 * @group fast
	 * @depends testAddRoute
	 */
	public function testRegexRoutes() {
		$args = [];
		$called = false;

		beatbox\Router::add_simple_routes(Map {
			'test/\d+' => Map {'page' => function(beatbox\Path $url, beatbox\Extension $ext,
													beatbox\Metadata $md) use(&$args, &$called) {
				$args = func_get_args();
				$called = true;
				return 'Hello';
			}}
		}, true);

		$actual = beatbox\Router::route('test/123', Vector {'page'});

		$this->assertTrue($called, 'Was our callback called');
		$this->assertEquals([ImmVector {'test', '123'}, null, Map {}], $args,
							'We should have a list of segments, no extension and no metadata');
		$this->assertEquals('Hello', $actual, 'Our callback correctly returned');

		// Test that overriding works properly
		beatbox\Router::add_simple_routes(Map {
			'test/\d+' => Map {'page' => function(beatbox\Path $url, beatbox\Extension $ext,
													beatbox\Metadata $md) use(&$args, &$called) {
				$args = array_reverse(func_get_args());
				$called = false;
				return 'Good bye';
			}}
		}, true);

		$actual = beatbox\Router::route('test/4', Vector {'page'});

		$this->assertFalse($called, 'Was our callback called');
		$this->assertEquals([Map {}, null, ImmVector {'test', '4'}], $args,
							'We should have a list of segments, no extension and no metadata');
		$this->assertEquals($actual, 'Good bye', 'Our callback correctly returned');
	}

	/**
	 * @group fast
	 * @depends testAddRoute
	 */
	public function testAddingMerge() {
		beatbox\Router::add_routes(Map {
			'home' => Pair {
				Map {
					'page' => fun('pageHandler')
				},
				Map {
					'CSRF' => true,
				}
			}
		});

		$routes = nullthrows(beatbox\Router::get_routes_for_path('home'));
		$this->assertMapsEqual(Map {'page' => 'pageHandler'}, $routes[0]);
		$this->assertMapsEqual(Map {'CSRF' => true}, $routes[1]);

		beatbox\Router::add_routes(Map {
			'home' => Pair {
				Map {
					'page' => fun('outerPageHandler'),
					'form' => fun('formHandler'),
				},
				Map {
					'CSRF' => '0',
					'MemberCheck' => 1,
				}
			}
		});

		$routes = nullthrows(beatbox\Router::get_routes_for_path('home'));
		$this->assertMapsEqual(Map {
			'page' => 'outerPageHandler',
			'form' => 'formHandler'
		}, $routes[0]);
		$this->assertMapsEqual(Map {'CSRF' => '0', 'MemberCheck' => 1}, $routes[1]);
	}

	/**
	 * @group sanity
	 * @depends testAddRoute
	 */
	public function testDefaultFragment() {
		$args = [];
		$called = false;

		beatbox\Router::add_simple_routes(Map {
			'home' => Map {'page' => function(beatbox\Path $url, beatbox\Extension $ext,
												beatbox\Metadata $md) use(&$args, &$called) {
				$args = func_get_args();
				$called = true;
				return 'Hello';
			}}
		});

		$response = beatbox\Router::route('home');

		$this->assertEquals('Hello', $response);
		$this->assertTrue($called);
		$this->assertEquals([ImmVector {'home'}, null, Map {}], $args);

		$args = [];
		$called = false;

		$response = beatbox\Router::route('home/extra');

		$this->assertEquals('Hello', $response);
		$this->assertTrue($called);
		$this->assertEquals([ImmVector {'home', 'extra'}, null, Map {}], $args);
	}

	/**
	 * @group fast
	 * @depends testAddRoute
	 */
	public function testAjaxResponse() {
		$args = [];
		$called = false;

		$nested = [];
		$nestedCalled = false;

		beatbox\Router::add_simple_routes(Map {
			'/' => Map {
				'a' => function(beatbox\Path $url, beatbox\Extension $ext,
								beatbox\Metadata $md) use(&$args, &$called) {
					$args = func_get_args();
					$called = true;
					return 'Hello';
				},
				'b' => function(beatbox\Path $url, beatbox\Extension $ext,
								beatbox\Metadata $md) use(&$nested, &$nestedCalled) {
					$nested = func_get_args();
					$nestedCalled = true;
					return 'World';
				}
			}
		});

		// Setup AJAX
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHTTPRequest';

		$response = (string)beatbox\Router::route('/', Vector {'a', 'b'});

		$this->assertTrue($called, 'The a callback was not called');
		$this->assertEquals([ImmVector {'/'}, null, Map {}], $args);
		$this->assertTrue($nestedCalled);
		$this->assertEquals([ImmVector {'/'}, null, Map {}], $nested);

		$response = json_decode($response, true);
		$this->assertEquals(['a' => 'Hello', 'b' => 'World'], $response);
	}

	/**
	 * @group fast
	 * @depends testAddRoute
	 * @depends testAjaxResponse
	 */
	public function testErrors() {
		// Check no routes
		try {
			beatbox\Router::route('non-existent');
			$this->fail('Should have thrown an error');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(404, $e->getBaseCode(), 'Should have a 404 error');
		}

		// Check too many fragments for non-ajax
		try {
			beatbox\Router::route('/', Vector { 'page', 'form', 'errors'});
			$this->fail('Should have thrown an error');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(400, $e->getBaseCode(), 'Should have a 400 error');
		}

		// Check no fragment
		beatbox\Router::add_simple_routes(Map {
			'/' => Map { 'page' => function(beatbox\Path $url, beatbox\Extension $ext,
											beatbox\Metadata $md) {} }
		});
		try {
			beatbox\Router::route('/', Vector {'form'});
			$this->fail('Should have thrown an error');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(404, $e->getBaseCode(), 'Should have a 404 error');
		}

		// Setup AJAX
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHTTPRequest';
		// Check no fragment
		try {
			beatbox\Router::route('/', Vector {'form'});
			$this->fail('Should have thrown an error');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(404, $e->getBaseCode(), 'Should have a 404 error');
		}

		// Check a single, missing fragment
		try {
			beatbox\Router::route('/', Vector {'page', 'form'});
			$this->fail('Should have thrown an error');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(404, $e->getBaseCode(), 'Should have a 404 error');
		}
	}

	/**
	 * @group fast
	 * @depends testAjaxResponse
	 */
	public function testXHP() {
		beatbox\Router::add_simple_routes(Map {
			'/' => Map { 'page' => function(beatbox\Path $url, beatbox\Extension $ext,
											beatbox\Metadata $md) { return <div></div>; } }
		});

		$response = (string)beatbox\Router::route('/');

		$this->assertEquals('<div></div>', $response);

		// Setup AJAX
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHTTPRequest';

		$response = json_decode((string)beatbox\Router::route('/'), true);
		$this->assertEquals(['page' => '<div></div>'], $response);
	}

	/**
	 * @group fast
	 * @depends testAddRoute
	 */
	public function testExtension() {
		$args = [];
		$called = false;

		beatbox\Router::add_simple_routes(Map {
			'home' => Map {'page' => function(beatbox\Path $url, beatbox\Extension $ext,
												beatbox\Metadata $md) use(&$args, &$called) {
				$args = func_get_args();
				$called = true;
				return 'Hello';
			}}
		});

		$res = beatbox\Router::route('home.php');
		$this->assertEquals('Hello', $res);
		$this->assertTrue($called);
		$this->assertEquals([ImmVector {'home'}, 'php', Map {}], $args);

		$args = [];
		$called = false;

		$res = beatbox\Router::route('home.somelongextension');
		$this->assertEquals('Hello', $res);
		$this->assertTrue($called);
		$this->assertEquals([ImmVector {'home'}, 'somelongextension', Map {}], $args);

		$args = [];
		$called = false;

		$res = beatbox\Router::route('home.tar.gz');
		$this->assertEquals('Hello', $res);
		$this->assertTrue($called);
		$this->assertEquals([ImmVector {'home'}, 'tar.gz', Map {}], $args);
	}

	/**
	 * @group fast
	 * @depends testAddRoute
	 */
	public function testAddChecker() {
		beatbox\Router::add_checker('test', cast_callable(__CLASS__ . '::testAddChecker'));
		beatbox\Router::add_checker('CSRF', cast_callable(__CLASS__ . '::testExtension'));

		// Check exact match
		$this->assertEquals(__CLASS__ . '::testAddChecker', beatbox\Router::get_checker('test'));
		$this->assertEquals(__CLASS__ . '::testExtension', beatbox\Router::get_checker('CSRF'));

		// Check different case
		$this->assertEquals(__CLASS__ . '::testAddChecker', beatbox\Router::get_checker('TEST'));

		// Check non-existent
		$this->assertEmpty(beatbox\Router::get_checker('none'));

		// Check override
		beatbox\Router::add_checker('test', cast_callable(__CLASS__ . '::testCheckPass'));
		$this->assertEquals(__CLASS__ . '::testCheckPass', beatbox\Router::get_checker('test'));
	}

	/**
	 * @group fast
	 * @depends testAddChecker
	 */
	public function testCheckPass() {
		$called = 0;

		beatbox\Router::add_checker('test', function($s, $m) use(&$called) {
			$called++;
			return true;
		});

		beatbox\Router::add_routes(Map {
			'home' => Pair {
				Map { 'page' => function(beatbox\Path $url, beatbox\Extension $ext,
										beatbox\Metadata $md) {}},
				Map { 'test' => true }
			}
		});

		beatbox\Router::route('home');
		$this->assertEquals(1, $called);

		// AJAX check. Should be called each time
		$called = 0;
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHTTPRequest';
		beatbox\Router::route('home', Vector {'page', 'page'});
		$this->assertEquals(2, $called);
	}

	/**
	 * @group fast
	 * @depends testAddChecker
	 */
	public function testCheckFail() {
		// Setup
		beatbox\Router::add_checker('test', function($s, $m) {
			return false;
		});
		beatbox\Router::add_routes(Map {
			'home' => Pair {
				Map { 'page' => function(beatbox\Path $url, beatbox\Extension $ext,
										beatbox\Metadata $md) {}},
				Map { 'test' => true }
			}
		});

		try {
			beatbox\Router::route('home');
			$this->fail('Should have thrown an error');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}

		beatbox\Router::add_routes(Map {
			'home' => Pair {
				Map { 'form' => function(beatbox\Path $url, beatbox\Extension $ext,
										beatbox\Metadata $md) {} },
				Map { 'test' => Vector {'form'} }
			}
		});

		beatbox\Router::route('home');

		try {
			beatbox\Router::route('home', Vector {'form'});
			$this->fail('Should have thrown an error');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}

		try {
			$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHTTPRequest';
			beatbox\Router::route('home', Vector {'page', 'form'});
			$this->fail('Should have thrown an error');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}
	}

	/**
	 * @group fast
	 * @depends testCheckPass
	 * @depends testCheckFail
	 */
	public function testCheckCascade() {
		// Use AJAX so we can have multiple fragments
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHTTPRequest';
		// Easier to check for failures, since passing is the default
		beatbox\Router::add_checker('test', function($s, $m) {
			return false;
		});
		// Base: three available fragments, all require test
		// First layer: none require test
		// Second layer: some require test
		// Third layer: different set require test
		// Fourth layer: they all require it again
		// Fifth layer: no change
		beatbox\Router::add_routes(Map {
			'base' => Pair {
				Map {
					'a' => function(beatbox\Path $url, beatbox\Extension $ext,
									beatbox\Metadata $md) {},
					'b' => function(beatbox\Path $url, beatbox\Extension $ext,
									beatbox\Metadata $md) {},
					'c' => function(beatbox\Path $url, beatbox\Extension $ext,
									beatbox\Metadata $md) {},
				},
				Map {
					'test' => true
				}
			},
			'base/first' => Pair {
				Map {},
				Map { 'test' => false }
			},
			'base/first/second' => Pair {
				Map {},
				Map { 'test' => ['a', 'b']}
			},
			'base/first/second/third' => Pair {
				Map {},
				Map { 'test' => Vector {'b', 'c'}}
			},
			'base/first/second/third/fourth' => Pair {
				Map {},
				Map { 'test' => true }
			},
			'base/first/second/third/fourth/fifth' => Pair {
				Map {},
				Map {}
			}
		});

		// Base check, all error
		try {
			beatbox\Router::route('base', Vector {'a'});
			$this->fail('Should have errored');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}
		try {
			beatbox\Router::route('base', Vector {'b'});
			$this->fail('Should have errored');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}
		try {
			beatbox\Router::route('base', Vector {'c'});
			$this->fail('Should have errored');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}

		// First layer, no exceptions
		beatbox\Router::route('base/first', Vector {'a', 'b', 'c'});

		// Second layer, a+b fail, c passes
		try {
			beatbox\Router::route('base/first/second', Vector {'a'});
			$this->fail('Should have errored');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}
		try {
			beatbox\Router::route('base/first/second', Vector {'b'});
			$this->fail('Should have errored');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}
		beatbox\Router::route('base/first/second', Vector {'c'});

		// Third layer, a passes, b+c fail
		beatbox\Router::route('base/first/second/third', Vector {'a'});
		try {
			beatbox\Router::route('base/first/second/third', Vector {'b'});
			$this->fail('Should have errored');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}
		try {
			beatbox\Router::route('base/first/second/third', Vector {'c'});
			$this->fail('Should have errored');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}

		// Fourth layer, all error
		try {
			beatbox\Router::route('base/first/second/third/fourth', Vector {'a'});
			$this->fail('Should have errored');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}
		try {
			beatbox\Router::route('base/first/second/third/fourth', Vector {'b'});
			$this->fail('Should have errored');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}
		try {
			beatbox\Router::route('base/first/second/third/fourth', Vector {'c'});
			$this->fail('Should have errored');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}

		// Fifth layer, all error
		try {
			beatbox\Router::route('base/first/second/third/fourth/fifth', Vector {'a'});
			$this->fail('Should have errored');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}
		try {
			beatbox\Router::route('base/first/second/third/fourth/fifth', Vector {'b'});
			$this->fail('Should have errored');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}
		try {
			beatbox\Router::route('base/first/second/third/fourth/fifth', Vector {'c'});
			$this->fail('Should have errored');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}
	}

	/**
	 * @group fast
	 * @depends testAddRoute
	 */
	public function testResponseForFragment() {
		// Setup base
		$args = [];
		$called = false;
		beatbox\Router::add_simple_routes(Map {
			'home' => Map {
				'page' => function(beatbox\Path $url, beatbox\Extension $ext,
									beatbox\Metadata $md) {},
				'a' => function(beatbox\Path $url, beatbox\Extension $ext,
									beatbox\Metadata $md) use(&$args, &$called) {
					$args = func_get_args();
					$called = true;
					return 'a';
				},
				'b' => function(beatbox\Path $url, beatbox\Extension $ext,
									beatbox\Metadata $md) use(&$args, &$called) {
					$args = func_get_args();
					$called = true;
					return 'b';
				},
				'c' => function(beatbox\Path $url, beatbox\Extension $ext,
									beatbox\Metadata $md) use(&$args, &$called) {
					$args = func_get_args();
					$called = true;
					return 'c';
				},
				'd' => function(beatbox\Path $url, beatbox\Extension $ext,
									beatbox\Metadata $md) use(&$args, &$called) {
					$args = func_get_args();
					$called = true;
					return 'd';
				}
			}
		});

		// Start the route
		beatbox\Router::route('home.text');

		$expectedArgs = [ImmVector {'home'}, 'text', Map {}];

		foreach(['a', 'b', 'c', 'd'] as $frag) {
			$args = [];
			$called = false;

			$res = beatbox\Router::response_for_fragment($frag);

			$this->assertEquals($frag, $res);
			$this->assertTrue($called);
			$this->assertEquals($expectedArgs, $args);
		}

		// Null check
		$this->assertNull(beatbox\Router::response_for_fragment('e'));
	}

	/**
	 * @group fast
	 * @depends testAddRoute
	 */
	public function testFragmentCallback() {
		beatbox\Router::add_simple_routes(Map {
			'/' => Map {'page' => function(beatbox\Path $url, beatbox\Extension $ext,
											beatbox\Metadata $md) {
				return new RouterTest_Callback();
			}}
		});

		$ret = beatbox\Router::route('/');
		invariant($ret instanceof RouterTest_Callback, '$ret should be a RouterTest_Callback');

		$this->assertEquals(ImmVector {'/'}, $ret->url);
		$this->assertEquals('page', $ret->fragment);
	}
}

class RouterTest_Callback implements beatbox\FragmentCallback {
	public $url, $fragment;

	public function forFragment(beatbox\Path $url, string $fragment) : mixed {
		$this->url = $url;
		$this->fragment = $fragment;
		return $this;
	}
}

class TestRouter extends beatbox\Router {
	<<Override>>
	public static function reset(): void {
		parent::reset();
	}
}
