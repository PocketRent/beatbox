<?hh // strict

namespace beatbox;

use Awaitable;

type Path = ImmVector<string>;
type Extension = ?string;
type Metadata = Map<string, mixed>;

type FragmentHandler = (function(Path, Extension, Metadata): mixed);
type CheckerCallback = (function(string, Metadata): bool);

type FragmentTable = Map<string, FragmentHandler>;
type CheckerTable = Map<string, CheckerCallback>;

type PathTable = Pair<FragmentTable, Metadata>;

type RouteTable = Map<string, PathTable>;
type SimpleRouteTable = Map<string, FragmentTable>;

newtype RequestStack = Vector<RequestStackItem>;

class Router {
	protected static RouteTable $_straight_routes = Map {};
	protected static RouteTable $_regex_routes = Map {};

	protected static CheckerTable $_checkers = Map {};

	protected static RequestStack $_stack = Vector {};

	/**
	 * Route the url, generating the fragments
	 */
	public static function route(string $url, \Traversable<string> $fragments = Vector {}) : mixed {
		// Get extension
		if (strpos($url, '.') === false) {
			$ext = '';
		} else {
			list($url, $ext) = explode('.', $url, 2);
		}

		// Get URL parts
		$url = trim($url, '/');
		$parts = explode('/', $url);
		if ($parts == ['']) {
			$parts = ['/'];
		}
		$parts = Vector::fromArray($parts);

		// Get fragments
		$fragments = new ImmVector($fragments);
		if ($fragments->count() == 0) {
			$fragments = ImmVector {'page'};
		}

		// Get potential routes and MD
		$url = $parts->toImmVector();
		$allPaths = Vector {};
		do {
			$path = implode('/', $parts);
			$allPaths[] = $path;
			$parts->pop();
		} while(!$parts->isEmpty());
		$allPaths[] = '';
		$allPaths->reverse();

		$paths = Map {};
		$metadata = Map {};

		$allPaths->map((string $path) ==> {
			$p = static::get_routes_for_path($path);
			if ($p) {
				$paths->setAll($p[0]);
				$metadata->setAll($p[1]);
			}
		});

		$stack = new RequestStackItem($url, $ext, $paths, $metadata);

		self::$_stack->add($stack);

		if (is_pagelet() || is_ajax()) {
			return static::route_ajax($fragments, $stack);
		} else {
			return static::route_base($fragments, $stack);
		}
	}

	/**
	 * Handle an AJAX/Pagelet route request
	 */
	protected static function route_ajax(ImmVector<string> $parts,
											RequestStackItem $stack): string {
		$fragments = $stack->getFragmentTable();
		$metadata = $stack->getMetaData();

		static::validate_fragments($parts, $fragments, $metadata);

		if ($parts->count() > 1 && can_pagelet('GET')) {
			$res = static::route_pagelet($parts, $stack);
		} else {
			$res = Map {};

			foreach ($parts as $name) {
				$res[$name] = static::render_fragment($name, $stack);

				if(is_object($res[$name]) && !$res[$name] instanceof \JsonSerializable &&
					!$res[$name] instanceof Collection) {
					$res[$name] = (string)$res[$name];
				}
			}
		}

		header("Content-type: application/json");
		return json_encode($res, \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT |
									\JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Handle a non-AJAX/Pagelet route request
	 */
	protected static function route_base(ImmVector<string> $parts, RequestStackItem $stack): mixed {
		$fragments = $stack->getFragmentTable();
		$metadata = $stack->getMetaData();

		if ($parts->count() != 1) {
			http_error(400, 'Non-ajax request cannot request more than one fragment');
		}

		static::validate_fragments($parts, $fragments, $metadata);

		$frag = $parts[0];

		return self::render_fragment($frag, $stack);
	}

	/**
	 * Handle an Pagelet route request
	 */
	protected static function route_pagelet(ImmVector<string> $parts,
											RequestStackItem $stack): Map<string, mixed> {
		$res = Map {};
		$tasks = Vector {};

		$url = implode('/', $stack->getPath());

		$task_headers = apache_request_headers() ?: [];
		$task_headers['X-Pagelet-Fragment'] = 'true';

		$data = filter_input_array(\INPUT_GET, 0, false);

		foreach ($parts as $frag) {
			// Override the previous 'fragments' value
			$data['fragments'] = $frag;

			// Convert to a query-string
			$q = http_build_query($data);

			$u = $url . '?' . $q;
			// Start a new task
			$t = pagelet_server_task_start($u, $task_headers);
			if (!$t) {
				throw new errors\Exception("Error starting pagelet task");
			}
			$tasks[] = Pair {$frag, $t};
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
				if ($result[0] == '{') {
					$obj = json_decode($result, true);
				} else {
					$obj = json_decode(gzdecode($result), true);
				}
				assert(is_array($obj));
				assert(count($obj) == 1);

				$res[$frag] = $obj[$frag];
			} else if ($code >= 300 && $code <= 399) {
				if ($redirects < 15) {
					$redirects++;
					$t = pagelet_server_task_start($headers['Location'], $task_headers);
					if (!$t) {
						throw new errors\Exception("Error starting pagelet task");
					}
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
	 * Checks that the requested fragments exist and can be called.
	 */
	protected static function validate_fragments(ImmVector<string> $fragments,
													FragmentTable $available,
													Metadata $md): void {
		$err = null;
		foreach ($fragments as $fragment) {
			if (!$available->contains($fragment)) {
				if ($err === null) {
					$err = new errors\HTTP_Exception('Fragment not found: ' . $fragment, 404);
				}
				$err->setHeader('Fragment', $fragment, false);
			}
		}
		if ($err) {
			throw $err;
		}

		foreach ($md as $key => $value) {
			if (!$value) {
				continue;
			}
			$checker = static::get_checker($key);
			if (!$checker) {
				continue;
			}
			if ($value instanceof Traversable) {
				$value = new ImmSet($value);
			}
			foreach ($fragments as $fragment) {
				if ($value instanceof ImmSet && !$value->contains($fragment)) {
					continue;
				}
				if (!$checker($fragment, $md)) {
					http_error(403);
				}
			}
		}
	}

	/**
	 * Handles the rendering of a potential Awaitable fragment
	 */
	protected static function render_fragment(string $fragName, RequestStackItem $stack): mixed {
		$url = $stack->getPath();
		$extension = $stack->getExtension();
		$md = $stack->getMetaData();
		$frag = $stack->getFragmentTable()->at($fragName);

		$val = $frag($url, $extension, $md);
		if ($val instanceof Awaitable) {
			$val = wait($val);
		}
		if ($val && $val instanceof FragmentCallback) {
			$val = $val->forFragment($url, $fragName);
			if ($val instanceof Awaitable) {
				$val = wait($val);
			}
		}
		return $val;
	}

	/**
	 * Add routes with metadata
	 */
	public static function add_routes(RouteTable $routes, bool $regex = false) : void {
		if ($regex) {
			$map = self::$_regex_routes;
		} else {
			$map = self::$_straight_routes;
		}
		foreach ($routes as $path => $table) {
			$path = self::sanitise_path($path);
			if (!$map->contains($path)) {
				$map[$path] = Pair { Map {}, Map {} };
			}
			// Add the fragment handlers
			$map[$path][0]->setAll($table[0]);

			// Add the metadata
			$map[$path][1]->setAll($table[1]);
		}
	}

	/**
	 * Add routes without metadata
	 */
	public static function add_simple_routes(SimpleRouteTable $routes, bool $regex = false): void {
		static::add_routes($routes->map((FragmentTable $t) ==> Pair {$t, Map {} }), $regex);
	}

	/**
	 * Gets the routes available for a given path
	 */
	public static function get_routes_for_path(string $path): ?PathTable {
		$path = self::sanitise_path($path);
		if (self::$_straight_routes->contains($path)) {
			return self::$_straight_routes[$path];
		}
		if ($path) {
			foreach (self::$_regex_routes as $p => $table) {
				if (preg_match('#^' . $p . '$#', $path)) {
					return $table;
				}
			}
		}
		return null;
	}

	/**
	 * Add a checker for the given metadata key
	 */
	public static function add_checker(string $key, CheckerCallback $callback) : void {
		self::$_checkers[strtolower($key)] = $callback;
	}

	/**
	 * Return the checker for the given metadata key
	 */
	public static function get_checker(string $key) : ?CheckerCallback {
		return self::$_checkers->get(strtolower($key));
	}

	/**
	 * Gets the response for a specific fragment
	 */
	public static function response_for_fragment(string $frag) : mixed {
		$stack = static::current_stack();
		if ($stack) {
			$frags = $stack->getFragmentTable();
			if ($frags->contains($frag)) {
				return static::render_fragment($frag, $stack);
			}
		}
		return null;
	}

	/**
	 * Returns the path currently being routed
	 */
	public static function current_path() : ?ImmVector<string> {
		$stack = static::current_stack();
		if ($stack) {
			return $stack->getPath();
		}
		return null;
	}

	/**
	 * Returns the current request on top of the stack
	 */
	protected static function current_stack(): ?RequestStackItem {
		if (!self::$_stack->isEmpty()) {
			$index = self::$_stack->count() - 1;
			return self::$_stack[$index];
		}
		return null;
	}

	/**
	 * Resets the router
	 */
	protected static function reset(): void {
		self::$_straight_routes = Map {};
		self::$_regex_routes = Map {};
		self::$_checkers = Map {};
		self::$_stack = Vector {};
	}

	/**
	 * Removes leading/trailing slashes from paths where it makes sense
	 */
	private static function sanitise_path(string $path): string {
		if (strlen($path)) {
			$path = trim($path, '/') ?: '/';
		}
		return $path;
	}

	/**
	 * How long to wait before a pagelet fragment request is forceably timed out
	 */
	private static function fragment_timeout(): int {
		if (defined('FRAGMENT_TIMEOUT')) {
			return constant('FRAGMENT_TIMEOUT');
		}
		return 500;
	}
}

class RequestStackItem {
	public function __construct(private Path $path, private Extension $extension,
								private FragmentTable $fragmentTable, private Metadata $metadata) {
		// no-op
	}

	public function getPath(): Path {
		return $this->path;
	}

	public function getExtension(): Extension {
		return $this->extension;
	}

	public function getFragmentTable(): FragmentTable {
		return $this->fragmentTable;
	}

	public function getMetaData(): Metadata {
		return $this->metadata;
	}

	public function __sleep(): array<string> {
		return array(
			'path',
			'extension',
		);
	}

	public function __wakeup(): void {
		// Get potential routes and MD
		$parts = $this->path->toVector();
		$allPaths = Vector {};
		do {
			$path = implode('/', $parts);
			$allPaths[] = $path;
			$parts->pop();
		} while(!$parts->isEmpty());
		$allPaths[] = '';
		$allPaths->reverse();

		$paths = Map {};
		$metadata = Map {};

		$allPaths->map((string $path) ==> {
			$p = Router::get_routes_for_path($path);
			if ($p) {
				$paths->setAll($p[0]);
				$metadata->setAll($p[1]);
			}
		});

		$this->fragmentTable = $paths;
		$this->metadata = $metadata;
	}
}
