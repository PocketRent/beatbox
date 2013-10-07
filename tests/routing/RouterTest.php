<?php

namespace pr\test;

use pr\base, Map, Pair, Vector;

class RouterTest extends base\Test {
	// Wipe the existing routes for each test
	public function setUp() {
		base\Router::reset();
	}

	/**
	 * @group sanity
	 */
	public function testEmpty() {
		$this->assertEquals(base\Router::get_routes_for_path('/'), Pair { Map {}, Map {}});
		$this->assertEquals(base\Router::get_routes_for_path('home'), Pair { Map {}, Map {}});
	}

	/**
	 * @group sanity
	 * @depends testEmpty
	 */
	public function testAddRoute() {
		base\Router::add_routes(Map {
			'/' => Map { 'page' => 'pageGenerator' },
			'home' => Pair { Map {'form' => 'homeForm', 'logout' => 'homeLogout'}, Map {'CSRF' => true}}
		});

		$this->assertEquals(
			base\Router::get_routes_for_path('/'),
			Pair { Map {'page' => 'pageGenerator'}, Map {}}
		);

		$this->assertEquals(
			base\Router::get_routes_for_path('home'),
			Pair { Map {'form' => 'homeForm', 'logout' => 'homeLogout'}, Map {'CSRF' => true}}
		);

		base\Router::add_routes(Map {
			'/' => Pair { Map {'form' => 'pageForm'}, Map{'CSRF' => true}},
			'home' => Pair{null, Map {'CSRF' => false}}
		});

		$this->assertEquals(
			base\Router::get_routes_for_path('/'),
			Pair { Map {'page' => 'pageGenerator', 'form' => 'pageForm'}, Map {'CSRF' => true}}
		);

		$this->assertEquals(
			base\Router::get_routes_for_path('home'),
			Pair { Map {'form' => 'homeForm', 'logout' => 'homeLogout'}, Map {'CSRF' => false}}
		);
	}

	/**
	 * @group fast
	 * @depends testAddRoute
	 */
	public function testReset() {
		base\Router::add_routes(Map {
			'/' => Map {'page' => 'pageGenerator'},
			'home' => Pair { Map {'form' => 'homeForm', 'logout' => 'homeLogout'}, Map {'CSRF' => true}}
		});
		base\Router::reset();
		$this->assertEquals(base\Router::get_routes_for_path('/'), Pair { Map {}, Map {}});
		$this->assertEquals(base\Router::get_routes_for_path('home'), Pair { Map {}, Map {}});
	}

	/**
	 * @group fast
	 * @depends testAddRoute
	 */
	public function testBaseRoute() {
		$args = [];
		$called = false;

		base\Router::add_routes(Map {
			'/' => Map {'page' => function() use(&$args, &$called) {
				$args = func_get_args();
				$called = true;
				return 'Hello';
			}}
		});

		$actual = base\Router::route('/', Vector {'page'});

		$this->assertTrue($called, 'Was our callback called');
		$this->assertEquals($args, [['/'], null, Map {}], 'We should have a list of segments, no extension and no metadata');
		$this->assertEquals($actual, 'Hello', 'Our callback correctly returned');

		// Test that overriding works properly
		base\Router::add_routes(Map {
			'/' => Map {'page' => function() use(&$args, &$called) {
				$args = array_reverse(func_get_args());
				$called = false;
				return 'Good bye';
			}}
		});

		$actual = base\Router::route('/', Vector {'page'});

		$this->assertFalse($called, 'Was our callback called');
		$this->assertEquals($args, [Map {}, null, ['/']], 'We should have a list of segments, no extension and no metadata');
		$this->assertEquals($actual, 'Good bye', 'Our callback correctly returned');
	}

	/**
	 * @group fast
	 * @depends testAddRoute
	 */
	public function testRegexRoutes() {
		$args = [];
		$called = false;

		base\Router::add_routes(Map {
			'test/\d+' => Map {'page' => function() use(&$args, &$called) {
				$args = func_get_args();
				$called = true;
				return 'Hello';
			}}
		}, true);

		$actual = base\Router::route('test/123', Vector {'page'});

		$this->assertTrue($called, 'Was our callback called');
		$this->assertEquals($args, [['test', '123'], null, Map {}], 'We should have a list of segments, no extension and no metadata');
		$this->assertEquals($actual, 'Hello', 'Our callback correctly returned');

		// Test that overriding works properly
		base\Router::add_routes(Map {
			'test/\d+' => Map {'page' => function() use(&$args, &$called) {
				$args = array_reverse(func_get_args());
				$called = false;
				return 'Good bye';
			}}
		}, true);

		$actual = base\Router::route('test/4', Vector {'page'});

		$this->assertFalse($called, 'Was our callback called');
		$this->assertEquals($args, [Map {}, null, ['test', '4']], 'We should have a list of segments, no extension and no metadata');
		$this->assertEquals($actual, 'Good bye', 'Our callback correctly returned');
	}

	/**
	 * @group fast
	 * @depends testAddRoute
	 */
	public function testAddingMerge() {
		base\Router::add_routes(Map {
			'home' => Pair {
				Map {
					'page' => 'pageHandler'
				},
				Map {
					'CSRF' => true,
				}
			}
		});

		$routes = base\Router::get_routes_for_path('home');
		$this->assertMapsEqual($routes[0], Map {'page' => 'pageHandler'});
		$this->assertMapsEqual($routes[1], Map {'CSRF' => true});

		base\Router::add_routes(Map {
			'home' => Pair {
				Map {
					'page' => 'outerPageHandler',
					'form' => 'formHandler',
				},
				Map {
					'CSRF' => '0',
					'MemberCheck' => 1,
				}
			}
		});

		$routes = base\Router::get_routes_for_path('home');
		$this->assertMapsEqual($routes[0], Map {'page' => 'outerPageHandler', 'form' => 'formHandler'});
		$this->assertMapsEqual($routes[1], Map {'CSRF' => '0', 'MemberCheck' => 1});
	}

	/**
	 * @group sanity
	 * @depends testAddRoute
	 */
	public function testDefaultFragment() {
		$args = [];
		$called = false;

		base\Router::add_routes(Map {
			'home' => Map {'page' => function() use(&$args, &$called) {
				$args = func_get_args();
				$called = true;
				return 'Hello';
			}}
		});

		$response = base\Router::route('home');

		$this->assertEquals($response, 'Hello');
		$this->assertTrue($called);
		$this->assertEquals($args, [['home'], null, Map {}]);

		$args = [];
		$called = false;

		$response = base\Router::route('home/extra');

		$this->assertEquals($response, 'Hello');
		$this->assertTrue($called);
		$this->assertEquals($args, [['home', 'extra'], null, Map {}]);
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

		base\Router::add_routes(Map {
			'/' => Map {
				'a' => function() use(&$args, &$called) {
					$args = func_get_args();
					$called = true;
					return 'Hello';
				},
				'b' => function() use(&$nested, &$nestedCalled) {
					$nested = func_get_args();
					$nestedCalled = true;
					return 'World';
				}
			}
		});

		// Setup AJAX
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHTTPRequest';

		$response = base\Router::route('/', Vector {'a', 'b'});

		$this->assertTrue($called, 'The a callback was not called');
		$this->assertEquals($args, [['/'], null, Map {}]);
		$this->assertTrue($nestedCalled);
		$this->assertEquals($nested, [['/'], null, Map {}]);

		$response = json_decode($response, true);
		$this->assertEquals($response, ['a' => 'Hello', 'b' => 'World']);
	}

	/**
	 * @group fast
	 * @depends testAddRoute
	 * @depends testAjaxResponse
	 */
	public function testErrors() {
		// Check no routes
		try {
			base\Router::route('non-existent');
			$this->fail('Should have thrown an error');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals($e->getBaseCode(), 404, 'Should have a 404 error');
		}

		// Check too many fragments for non-ajax
		try {
			base\Router::route('/', Vector { 'page', 'form', 'errors'});
			$this->fail('Should have thrown an error');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals($e->getBaseCode(), 400, 'Should have a 400 error');
		}

		// Check no fragment
		base\Router::add_routes(Map {
			'/' => Map { 'page' => function() {} }
		});
		try {
			base\Router::route('/', vector {'form'});
			$this->fail('Should have thrown an error');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals($e->getBaseCode(), 404, 'Should have a 404 error');
		}

		// Setup AJAX
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHTTPRequest';
		// Check no fragment
		try {
			base\Router::route('/', vector {'form'});
			$this->fail('Should have thrown an error');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals($e->getBaseCode(), 404, 'Should have a 404 error');
		}

		// Check a single, missing fragment
		try {
			base\Router::route('/', vector {'page', 'form'});
			$this->fail('Should have thrown an error');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals($e->getBaseCode(), 404, 'Should have a 404 error');
		}
	}

	/**
	 * @group fast
	 * @depends testAjaxResponse
	 */
	public function testXHP() {
		base\Router::add_routes(Map {
			'/' => Map { 'page' => function() { return <div></div>; } }
		});

		$response = base\Router::route('/');

		$this->assertEquals($response, '<div></div>');

		// Setup AJAX
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHTTPRequest';

		$response = json_decode(base\Router::route('/'), true);
		$this->assertEquals($response, ['page' => '<div></div>']);
	}

	/**
	 * @group fast
	 * @depends testAddRoute
	 */
	public function testExtension() {
		$args = [];
		$called = false;

		base\Router::add_routes(Map {
			'home' => Map {'page' => function() use(&$args, &$called) {
				$args = func_get_args();
				$called = true;
				return 'Hello';
			}}
		});

		$res = base\Router::route('home.php');
		$this->assertEquals($res, 'Hello');
		$this->assertTrue($called);
		$this->assertEquals($args, [['home'], 'php', Map {}]);

		$args = [];
		$called = false;

		$res = base\Router::route('home.somelongextension');
		$this->assertEquals($res, 'Hello');
		$this->assertTrue($called);
		$this->assertEquals($args, [['home'], 'somelongextension', Map {}]);

		$args = [];
		$called = false;

		$res = base\Router::route('home.tar.gz');
		$this->assertEquals($res, 'Hello');
		$this->assertTrue($called);
		$this->assertEquals($args, [['home'], 'tar.gz', Map {}]);
	}

	/**
	 * @group fast
	 * @depends testAddRoute
	 */
	public function testAddChecker() {
		base\Router::add_checker('test', 'pr\\test\RouterTest::testAddChecker');
		base\Router::add_checker('CSRF', 'pr\test\RouterTest::testExtension');

		// Check exact match
		$this->assertEquals(base\Router::get_checker('test'), 'pr\\test\RouterTest::testAddChecker');
		$this->assertEquals(base\Router::get_checker('CSRF'), 'pr\test\RouterTest::testExtension');

		// Check different case
		$this->assertEquals(base\Router::get_checker('TEST'), 'pr\\test\RouterTest::testAddChecker');

		// Check non-existent
		$this->assertEmpty(base\Router::get_checker('none'));

		// Check override
		base\Router::add_checker('test', 'pr\test\RouterTest::testCheckPass');
		$this->assertEquals(base\Router::get_checker('test'), 'pr\test\RouterTest::testCheckPass');
	}

	/**
	 * @group fast
	 * @depends testAddChecker
	 */
	public function testCheckPass() {
		$called = 0;

		base\Router::add_checker('test', function() use(&$called) {
			$called++;
			return true;
		});

		base\Router::add_routes(Map {
			'home' => Pair {
				Map { 'page' => function () {}},
				Map { 'test' => true }
			}
		});

		base\Router::route('home');
		$this->assertEquals($called, 1);

		// AJAX check. Should be called each time
		$called = 0;
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHTTPRequest';
		base\Router::route('home', Vector {'page', 'page'});
		$this->assertEquals($called, 2);
	}

	/**
	 * @group fast
	 * @depends testAddChecker
	 */
	public function testCheckFail() {
		// Setup
		base\Router::add_checker('test', function() {
			return false;
		});
		base\Router::add_routes(Map {
			'home' => Pair {
				Map { 'page' => function () {}},
				Map { 'test' => true }
			}
		});

		try {
			base\Router::route('home');
			$this->fail('Should have thrown an error');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals($e->getBaseCode(), 403);
		}

		base\Router::add_routes(Map {
			'home' => Pair {
				Map { 'form' => function () {} },
				Map { 'test' => Vector {'form'} }
			}
		});

		base\Router::route('home');

		try {
			base\Router::route('home', Vector {'form'});
			$this->fail('Should have thrown an error');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals($e->getBaseCode(), 403);
		}

		try {
			$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHTTPRequest';
			base\Router::route('home', Vector {'page', 'form'});
			$this->fail('Should have thrown an error');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals($e->getBaseCode(), 403);
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
		base\Router::add_checker('test', function() {
			return false;
		});
		// Base: three available fragments, all require test
		// First layer: none require test
		// Second layer: some require test
		// Third layer: different set require test
		// Fourth layer: they all require it again
		// Fifth layer: no change
		base\Router::add_routes(Map {
			'base' => Pair {
				Map {
					'a' => function () {},
					'b' => function () {},
					'c' => function () {},
				},
				Map {
					'test' => true
				}
			},
			'base/first' => Pair {
				null,
				Map { 'test' => false }
			},
			'base/first/second' => Pair {
				null,
				Map { 'test' => ['a', 'b']}
			},
			'base/first/second/third' => Pair {
				null,
				Map { 'test' => Vector {'b', 'c'}}
			},
			'base/first/second/third/fourth' => Pair {
				null,
				Map { 'test' => true }
			},
			'base/first/second/third/fourth/fifth' => Pair {
				null,
				null
			}
		});

		// Base check, all error
		try {
			base\Router::route('base', Vector {'a'});
			$this->fail('Should have errored');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}
		try {
			base\Router::route('base', Vector {'b'});
			$this->fail('Should have errored');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}
		try {
			base\Router::route('base', Vector {'c'});
			$this->fail('Should have errored');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}

		// First layer, no exceptions
		base\Router::route('base/first', Vector {'a', 'b', 'c'});

		// Second layer, a+b fail, c passes
		try {
			base\Router::route('base/first/second', Vector {'a'});
			$this->fail('Should have errored');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}
		try {
			base\Router::route('base/first/second', Vector {'b'});
			$this->fail('Should have errored');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}
		base\Router::route('base/first/second', Vector {'c'});

		// Third layer, a passes, b+c fail
		base\Router::route('base/first/second/third', Vector {'a'});
		try {
			base\Router::route('base/first/second/third', Vector {'b'});
			$this->fail('Should have errored');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}
		try {
			base\Router::route('base/first/second/third', Vector {'c'});
			$this->fail('Should have errored');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}

		// Fourth layer, all error
		try {
			base\Router::route('base/first/second/third/fourth', Vector {'a'});
			$this->fail('Should have errored');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}
		try {
			base\Router::route('base/first/second/third/fourth', Vector {'b'});
			$this->fail('Should have errored');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}
		try {
			base\Router::route('base/first/second/third/fourth', Vector {'c'});
			$this->fail('Should have errored');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}

		// Fifth layer, all error
		try {
			base\Router::route('base/first/second/third/fourth/fifth', Vector {'a'});
			$this->fail('Should have errored');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}
		try {
			base\Router::route('base/first/second/third/fourth/fifth', Vector {'b'});
			$this->fail('Should have errored');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals(403, $e->getBaseCode());
		}
		try {
			base\Router::route('base/first/second/third/fourth/fifth', Vector {'c'});
			$this->fail('Should have errored');
		} catch(base\errors\HTTP_Exception $e) {
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
		base\Router::add_routes(Map {
			'home' => Map {
				'page' => function() {},
				'a' => function() use(&$args, &$called) {
					$args = func_get_args();
					$called = true;
					return 'a';
				},
				'b' => function() use(&$args, &$called) {
					$args = func_get_args();
					$called = true;
					return 'b';
				},
				'c' => function() use(&$args, &$called) {
					$args = func_get_args();
					$called = true;
					return 'c';
				},
				'd' => function() use(&$args, &$called) {
					$args = func_get_args();
					$called = true;
					return 'd';
				}
			}
		});

		// Start the route
		base\Router::route('home.text');

		$expectedArgs = [['home'], 'text', Map {}];

		foreach(['a', 'b', 'c', 'd'] as $frag) {
			$args = [];
			$called = false;

			$res = base\Router::response_for_fragment($frag);

			$this->assertEquals($res, $frag);
			$this->assertTrue($called);
			$this->assertEquals($args, $expectedArgs);
		}

		// Null check
		$this->assertNull(base\Router::response_for_fragment('e'));
	}

	/**
	 * @group fast
	 * @depends testAddRoute
	 */
	public function testFragmentCallback() {
		base\Router::add_routes(Map {
			'/' => Map {'page' => function() {
				return new RouterTest_Callback;
			}}
		});

		$ret = base\Router::route('/');

		$this->assertEquals($ret->url, ['/']);
		$this->assertEquals($ret->fragment, 'page');
	}
}

class RouterTest_Callback implements base\FragmentCallback {
	public $url, $fragment;

	public function forFragment(\Traversable $url, \string $fragment) {
		$this->url = $url;
		$this->fragment = $fragment;
		return $this;
	}
}
