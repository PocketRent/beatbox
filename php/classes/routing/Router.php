<?hh

namespace beatbox;

use Map, Pair, Vector;

class Router {
	protected static Map<\string, Pair<Map<\string, (function(array, ?\string, \Map):\mixed)>, Map<\string>>> $routes = Map {};

	protected static Map<\string, Pair<Map<\string, (function(array, ?\string, \Map):\mixed)>, Map<\string>>> $regex_routes = Map {};

	protected static Map<\string, (function(\string, \Map):\bool)> $checkers = Map {};

	protected static Map<\string> $last_md = Map {};

	protected static Map<\string, (function(array, ?\string, \Map):\mixed)> $last_frags = Map {};

	protected static array $last_url = [];

	protected static ?\string $last_ext = null;

	/**
	 * Route the url, generating the fragments
	 */
	public static function route(\string $url, Vector<\string> $fragments = Vector {}) : \mixed {
		if(!count($fragments)) {
			$fragments = Vector {'page'};
		}
		$url = trim($url, '/');
		if(strpos($url, '.') !== false) {
			$pos = strpos($url, '.');
			$ext = substr($url, $pos+1);
			$url = substr($url, 0, $pos);
		} else {
			$ext = '';
		}
		if($url) {
			$parts = explode('/', $url);
		} else {
			$parts = ['/'];
		}
		$url = $parts;
		$available = [];
		$md = [];
		while($parts) {
			$path = implode('/', $parts);

			$available = array_merge(self::get_routes_for_path($path)[0]->toArray(), $available);
			$md = array_merge(self::get_routes_for_path($path)[1]->toArray(), $md);

			array_pop($parts);
		}

		$available = array_merge(self::get_routes_for_path('')[0]->toArray(), $available);
		$md = array_merge(self::get_routes_for_path('')[1]->toArray(), $md);

		$available = Map::fromArray($available);
		$md = Map::fromArray($md);

		self::$last_frags = $available;
		self::$last_md = $md;
		self::$last_url = $url;
		self::$last_ext = $ext;

		if(is_ajax() || is_pagelet()) {

			$err = null;
			foreach ($fragments as $frag) {
				if (empty($available[$frag])) {
					$err = $err ?: new errors\HTTP_Exception('Fragments not found', 404);
					$err->setHeader("Fragment", $frag, false);
				}
			}
			if ($err) throw $err;

			// If there are multiple fragments, this is a get request and the pagelet server is
			// enabled, process the fragments in parallel
			if (count($fragments) > 1 && is_get() && pagelet_server_is_enabled() &&
					!defined('RUNNING_TEST')) { // Because of the way the pagelets work, they don't play nice with the tests
				$res = self::process_pagelet_fragments($url, $fragments);
			} else {
				$res = [];
				foreach ($fragments as $frag) {
					self::check_frag($frag, $md);

					$res[$frag] = self::render_fragment($frag, $available[$frag], $url, $ext, $md);
					// This mostly handles XHP objects
					if(is_object($res[$frag]) && !$res[$frag] instanceof \JsonSerializable && !$res[$frag] instanceof \Collection) {
						$res[$frag] = (string)$res[$frag];
					}
				}
			}

			header("Content-type: application/json");
			return json_encode($res, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
		} else {
			if(count($fragments) > 1) {
				http_error(400, 'Non-ajax request cannot request more than one fragment');
			}
			$frag = $fragments[0];
			if(empty($available[$frag])) {
				http_error(404, 'Fragment not found');
			}

			self::check_frag($frag, $md);

			return self::render_fragment($frag, $available[$frag], $url, $ext, $md);
		}
	}

	/**
	 * Uses the HipHop pagelet server to process each fragment in parallel.
	 *
	 * @returns an array of fragment-name => content
	 */
	public static function process_pagelet_fragments(\string $url, Vector<\string> $fragments) : array {
		assert(pagelet_server_is_enabled() && is_get());
		$res = [];
		$tasks = [];

		$url = implode($url, '/');

		$task_headers = apache_request_headers() ?: [];
		// is_pagelet looks for this header
		$task_headers['X-Pagelet-Fragment'] = 'true';

		// Copy the GET parameters, as we'll need them later
		$data = $_GET;

		foreach ($fragments as $frag) {
			// Override the previous 'fragments' value
			$data['fragments'] = $frag;

			// Convert to a query-string
			$q = http_build_query($data);

			$u = $url . '?' . $q;
			// Start a new task
			$t = pagelet_server_task_start($u, $task_headers);
			if (!$t)
				throw new errors\Exception("Error starting pagelet task");
			$tasks[] = [$frag, $t];
		}

		// Keep track of the total number of redirects
		$redirects = 0;
		while (count($tasks) > 0) {
			list($frag, $t) = array_shift($tasks);
			$headers = []; $code = 0;
			// Block waiting for the task to finish
			$result = pagelet_server_task_result($t, $headers, $code, self::fragment_timeout());

			// Handle the response
			if ($code == -1) {
				http_error(598, 'Fragment task timed out');
			} else if ($code == 200) {
				// Pagelet requests are returned as json objects with a single key,
				// the name of the fragment.
				// We need to do this to ensure that processing the fragments using
				// pagelets has the same result as processing them serially
				$obj = json_decode($result, true);
				assert(count($obj) == 1);

				$res[$frag] = $obj[$frag];
			} else if ($code >= 300 && $code <= 399) {
				if ($redirects < 15) {
					$redirects++;
					$t = pagelet_server_task_start($headers['Location'], $task_headers);
					if (!$t)
						throw new errors\Exception("Error starting pagelet task");
					$tasks[] = [$frag, $t];
				} else {
					http_error(508, "Too many redirects");
				}
			} else {
				http_error($code, 'Fragment Error: '.$result);
			}
		}

		return $res;
	}

	/**
	 * Clear all the known routes and checkers
	 */
	public static function reset() : \void {
		self::$routes = Map {};
		self::$regex_routes = Map {};
		self::$checkers = Map {};
		self::$last_md = Map {};
		self::$last_frags = Map {};
		self::$last_url = [];
		self::$last_ext = null;
	}

	/**
	 * Add routes
	 */
	public static function add_routes(Map<\string> $routes, \boolean $regex = false) : \void {
		foreach($routes as $path => $route) {
			$l = strlen($path);
			$path = trim($path, '/');
			if(!$path && $l) {
				$path = '/';
			}
			if($route instanceof Pair) {
				list($fragments, $md) = $route;
			} else {
				$fragments = $route;
				$md = null;
			}
			if($regex) {
				$base = isset(self::$regex_routes[$path]) ? self::$regex_routes[$path] : Pair { new Map(), new Map() };
			} else {
				$base = isset(self::$routes[$path]) ? self::$routes[$path] : Pair { new Map(), new Map() };
			}
			if($fragments) {
				$base[0]->setAll($fragments);
			}
			if($md) {
				$base[1]->setAll($md);
			}
			if($regex) {
				self::$regex_routes[$path] = $base;
			} else {
				self::$routes[$path] = $base;
			}
		}
	}

	/**
	 * Gets the routes available for a given path
	 */
	public static function get_routes_for_path(\string $path) : Pair<Map<\string, \callable>, Map<\string>> {
		$l = strlen($path);
		$path = trim($path, '/');
		if(!$path && $l) {
			$path = '/';
		}
		if(isset(self::$routes[$path])) {
			return self::$routes[$path];
		} else if($path) {
			foreach(self::$regex_routes as $p => $v) {
				if(preg_match('#^' . $p . '$#', $path)) {
					return $v;
				}
			}
		}
		return Pair { new Map(), new Map() };
	}

	/**
	 * Add a checker for the given metadata key
	 */
	public static function add_checker(\string $key, \callable $callback) : \void {
		self::$checkers[strtolower($key)] = $callback;
	}

	/**
	 * Return the checker for the given metadata key
	 */
	public static function get_checker(\string $key) : \callable {
		$key = strtolower($key);
		if(isset(self::$checkers[$key])) {
			return self::$checkers[$key];
		}
		return null;
	}

	/**
	 * Check if we're allowed to access the current fragment based on the metadata
	 */
	protected static function check_frag(\string $frag, Map<string> $md) : \void {
		foreach($md as $key => $val) {
			if(!$val) {
				continue;
			}
			$checker = self::get_checker($key);
			if(!$checker) {
				continue;
			}
			if(is_array($val)) {
				if(!in_array($frag, $val)) {
					continue;
				}
			} else if($val instanceof Vector) {
				if($val->linearSearch($frag) == -1) {
					continue;
				}
			}
			if(!call_user_func($checker, $frag, $md)) {
				http_error(403);
			}
		}
	}

	protected static function render_fragment(\string $fragName, \callable $frag, array $url, \string $extension, Map $md) : \mixed {
		$val = call_user_func($frag, $url, $extension, $md);
		// If the response from the fragment is awaitable, then block on it here. This is a nice
		// convenience for fragment writers, meaning they can write fragments as async functions
		// when that is easier to work with.
		if ($val instanceof \Awaitable) {
			$val = $val->getWaithandle()->join();
		}
		if($val && $val instanceof \beatbox\FragmentCallback) {
			$val = $val->forFragment($url, $fragName);
			if ($val instanceof \Awaitable) {
				$val = $val->getWaithandle()->join();
			}
		}
		return $val;
	}

	public static function response_for_fragment(\string $frag) : \mixed {
		if(isset(self::$last_frags[$frag])) {
			return self::render_fragment($frag, self::$last_frags[$frag], self::$last_url, self::$last_ext, self::$last_md);
		}
		return null;
	}

	public static function current_path() : array {
		return self::$last_url;
	}

	protected static function fragment_timeout() : \int {
		if (defined('FRAGMENT_TIMEOUT'))
			return FRAGMENT_TIMEOUT;
		return 100;
	}
}
