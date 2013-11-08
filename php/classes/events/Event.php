<?hh

namespace beatbox;

use Map, List;

class Event {
	protected static $exact_listeners = Map <\string, List <callable>> {};
	protected static $prefix_listeners = Map <\string, List <callable>> {};

	protected $name;
	protected $args;

	/**
	 * Attach the given callback to the named events.
	 *
	 * $name can either be a single event or a list of events. If $prefix is
	 * true, then the callback is called on all events with a prefix in $name
	 */
	public static function attach_listener(callable $callback, \string $name, \bool $prefix = false) {
		if($prefix) {
			if(!isset(self::$prefix_listeners[$name])) {
				self::$prefix_listeners[$name] = List<callable> {$callback};
			} else {
				self::$prefix_listeners[$name][] = $callback;
			}
		} else {
			if(!isset(self::$exact_listeners[$name])) {
				self::$exact_listeners[$name] = List<callable> {$callback};
			} else {
				self::$exact_listeners[$name][] = $callback;
			}
		}
	}

	protected static function listeners_for(\string $name) : \Continuation {
		foreach(self::$exact_listeners as $key => $cbs) {
			if($key == $name) {
				foreach($cbs as $cb) yield $cb;
			}
		}
		foreach(self::$prefix_listeners as $key => $cbs) {
			if(substr($name, 0, strlen($key)) == $key) {
				foreach($cbs as $cb) yield $cb;
			}
		}
	}

	/**
	 * Create a new event of the given name
	 */
	public function __construct(\string $name /*, $args, ... */) {
		$this->args = func_get_args();
		$this->name = array_shift($this->args);
	}

	/**
	 * Send the event out to be processed asynchronously
	 */
	public function send() : \void {
		add_task([get_called_class(), 'async_run'], $this->name, $this->args);
	}

	/**
	 * Send the event out to be processed synchronously
	 */
	public function blockSend() : List<\mixed> {
		$vals = List<\mixed> {};
		foreach(self::listeners_for($this->name) as $cb) {
			$vals[] = call_user_func_array($cb, $this->args);
		}
		return $vals;
	}

	/**
	 * Async endpoint
	 */
	public static function async_run(\string $name, array $args) : List<\mixed> {
		$vals = List<\mixed> {};
		foreach(self::listeners_for($name) as $cb) {
			$vals[] = call_user_func_array($cb, $args);
		}
		return $vals;
	}

	public static function reset() {
		self::$exact_listeners = Map <\string, List <callable>> {};
		self::$prefix_listeners = Map <\string, List <callable>> {};
	}
}
