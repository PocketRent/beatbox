<?hh

namespace beatbox;

use \Redis as R;

class Task implements \Serializable {
	use redis;

	const CON_ALWAYS = 0;
	const CON_DIFF = 1;
	const CON_NEVER = 2;

	const QUEUE_NAME = 'bb:queue';

	protected $callback;

	protected $arguments;

	protected $policy = self::CON_ALWAYS;

	protected static function config_redis(R $r) {
		$r->setOption(R::OPT_SERIALIZER, R::SERIALIZER_NONE);
		$r->select(REDIS_DB_TASKS);
	}

	/**
	 * Load a task from the queue and run it
	 */
	public static function run() {
		$task = self::redis()->lpop(self::QUEUE_NAME);
		if($task) {
			$task = unserialize($task);
			if($task->canStart()) {
				return $task->start();
			} else {
				$task->queue();
			}
		}
	}

	/**
	 * Construct a new task, that calls the given callback with the given arguments
	 *
	 * The callback must be a method or function. It cannot be a closure, as it may not run
	 * in the current process.
	 */
	public function __construct(\callable $callback/*, $arguments...*/) {
		if($callback instanceof \Closure) {
			throw new \InvalidArgumentException('Task passed unserializable callback');
		}
		// We serialize here to do a deep copy
		$this->callback = serialize($callback);
		$arguments = func_get_args();
		array_shift($arguments);
		$this->arguments = serialize($arguments);
	}

	/**
	 * Set the concurrent policy used for this task
	 *
	 * Valid options are always, different args and never.
	 */
	public function setConcurrent(\int $policy) : Task {
		if(!in_array($policy, [self::CON_ALWAYS, self::CON_NEVER, self::CON_DIFF])) {
			throw new \InvalidArgumentException('Concurrency policy must be one of the CON_ constants');
		}
		$this->policy = $policy;
		return $this;
	}

	/**
	 * Add this task to the queue
	 *
	 * Returns the length of the queue
	 */
	public function queue() : \int {
		return self::redis()->rpush(self::QUEUE_NAME, serialize($this));
	}

	/**
	 * Checks if this task can start or not
	 */
	public function canStart() {
		$n = self::QUEUE_NAME . ':' . $this->callback;
		$n_c = $n . ':count';
		$n_a = $n . ':' . $this->arguments;
		$n_a_c = $n_a . ':count';

		self::redis()->multi();
		self::redis()->get($n);
		self::redis()->get($n_a);
		self::redis()->get($n_c);
		self::redis()->get($n_a_c);
		$vals = self::redis()->exec();
		if($vals[0] || $vals[1]) {
			// One of the options is blocked
			return false;
		}
		if($this->policy == self::CON_DIFF) {
			return !$vals[3];
		}
		if($this->policy == self::CON_NEVER) {
			return !$vals[2];
		}
		return true;
	}

	/**
	 * Runs the tasks
	 */
	public function start() {
		if(!$this->setUp()) {
			$this->queue();
			return null;
		}
		try {
			$ret = call_user_func_array(unserialize($this->callback), unserialize($this->arguments));
		} catch(\Exception $e) {
			$this->tearDown();
			throw $e;
		}
		$this->tearDown();
		return $ret === null ? true : $ret;
	}

	/**
	 * Set up this task (state that it's running)
	 *
	 * Assumes that it is allowed to start
	 */
	public function setUp() {
		$n = self::QUEUE_NAME . ':' . $this->callback;
		$n_c = $n . ':count';
		$n_a = $n . ':' . $this->arguments;
		$n_a_c = $n_a . ':count';

		self::redis()->multi();
		self::redis()->incr($n_a_c);
		self::redis()->incr($n_c);
		if($this->policy == self::CON_DIFF) {
			self::redis()->setnx($n_a, 1);
		} elseif($this->policy == self::CON_NEVER) {
			self::redis()->setnx($n, 1);
		}
		$vals = self::redis()->exec();
		if($this->policy != self::CON_ALWAYS && $vals[2] == 0) {
			self::redis()->multi();
			self::redis()->incr($n_a_c);
			self::redis()->incr($n_c);
			self::redis()->exec();
			return false;
		}
		return true;
	}

	/**
	 * Tear down after the task (state that it's finished)
	 */
	public function tearDown() {
		$n = self::QUEUE_NAME . ':' . $this->callback;
		$n_c = $n . ':count';
		$n_a = $n . ':' . $this->arguments;
		$n_a_c = $n_a . ':count';

		self::redis()->multi();
		self::redis()->decr($n_a_c);
		self::redis()->decr($n_c);
		if($this->policy == self::CON_DIFF) {
			self::redis()->set($n_a, 0);
		} elseif($this->policy == self::CON_NEVER) {
			self::redis()->set($n, 0);
		}
		self::redis()->exec();
	}

	/**
	 * Serialize this object. Should not be called directly.
	 */
	public function serialize() : \string {
		$data = ['c' => unserialize($this->callback), 'a' => unserialize($this->arguments), 'p' => $this->policy];
		return serialize($data);
	}

	/**
	 * Unserialize this object. Should not be called directly.
	 */
	public function unserialize($data) {
		$data = unserialize($data);
		if(empty($data['c'])) {
			throw new \InvalidArgumentException('Unserialize not passed a callback');
		}
		$this->callback = serialize($data['c']);
		$this->arguments = serialize($data['a']);
		$this->setConcurrent($data['p']);
	}
}
