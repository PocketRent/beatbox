<?hh // strict

namespace beatbox;

type CallbackFunction = (function(...):\mixed);

class Event {
	protected static Map<\string, Vector<CallbackFunction>> $exact_listeners = Map {};
	protected static Map<\string, Vector<CallbackFunction>> $prefix_listeners = Map {};

	protected \string $name;
	protected array<\mixed> $args;

	/**
	 * Attach the given callback to the named events.
	 *
	 * $name can either be a single event or a list of events. If $prefix is
	 * true, then the callback is called on all events with a prefix in $name
	 */
	public static function attach_listener(CallbackFunction $callback, \string $name,
											\bool $prefix = false): \void {
		if($prefix) {
			if(!self::$prefix_listeners->contains($name)) {
				self::$prefix_listeners[$name] = Vector {$callback};
			} else {
				self::$prefix_listeners[$name]->add($callback);
			}
		} else {
			if(!self::$exact_listeners->contains($name)) {
				self::$exact_listeners[$name] = Vector {$callback};
			} else {
				self::$exact_listeners[$name]->add($callback);
			}
		}
	}

	protected static function listeners_for(\string $name) : \Continuation<CallbackFunction> {
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
	public function __construct(\string $name,...) {
		$this->args = func_get_args();
		$this->name = array_shift($this->args);
	}

	/**
	 * Send the event out to be processed asynchronously
	 */
	public function send() : \void {
		add_task(cast_callable([get_called_class(), 'async_run']), $this->name, $this->args);
	}

	/**
	 * Send the event out to be processed synchronously
	 */
	public function blockSend() : Vector<\mixed> {
		$vals = Vector {};
		foreach(self::listeners_for($this->name) as $cb) {
			$vals->add(call_user_func_array($cb, $this->args));
		}
		return $vals;
	}

	/**
	 * Async endpoint
	 */
	public static function async_run(\string $name, array<mixed> $args) : Vector<\mixed> {
		$vals = Vector {};
		foreach(self::listeners_for($name) as $cb) {
			$vals->add(call_user_func_array($cb, $args));
		}
		return $vals;
	}

	public static function reset(): \void {
		self::$exact_listeners = Map {};
		self::$prefix_listeners = Map {};
	}
}
