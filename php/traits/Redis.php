<?hh

/** Redis Databases **/
// Default database, shouldn't normally be used
const int REDIS_DB_DEFAULT  = 0;
// Cache database, should only be used by the
// cache implementation.
const int REDIS_DB_CACHE    = 1;
// Session database
const int REDIS_DB_SETTINGS = 2;
// Tasks database
const int REDIS_DB_TASKS    = 3;
// Test database
const int REDIS_DB_TEST     = 15;

namespace beatbox;

use \Redis as R;

trait Redis {
	abstract protected static function config_redis(R $redis) : void;

	private static ?R $__redis__inst = null;

	/**
	 * Gets a redis object to do operations on
	 */
	protected static function redis() : R {
		if(!self::$__redis__inst) {
			$inst = new R();
			if (!$inst->connect(REDIS_SERVER))
				throw new \Exception("Could not connect to Redis server");
			if(REDIS_PASSWORD) {
				if (!$inst->auth(REDIS_PASSWORD))
					throw new \Exception("Could not authenticate with Redis server");
			}
			if(APP_NAME) {
				$inst->setOption(R::OPT_PREFIX, APP_NAME);
			}
			self::config_redis($inst);

			if (defined('RUNNING_TEST')) {
				// The test runner should always use the test database,
				// which we clear on connection
				$inst->select(REDIS_DB_TEST);
				$inst->flushdb();
			}
			self::$__redis__inst = $inst;
		}
		return self::$__redis__inst;
	}

	/**
	 * Takes a callable that is executed between Redis
	 * MULTI and EXEC commands. The callable is passed
	 * a redis instance to use.
	 *
	 * @returns the returned value from $fn
	 */
	protected static function redis_transaction<T>((function(R):T) $fn) : T {
		$r = self::redis();
		$r->multi();
		$val = call_user_func($fn, $r);
		$r->exec();
		return $val;
	}
}
