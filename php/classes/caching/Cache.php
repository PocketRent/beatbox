<?hh

namespace beatbox;

use \HH\Traversable;
use \Redis as R;

class Cache {
	use Redis;

	const DEFAULT_EXPIRE = 21600; // Default expiration is 6 hours

	protected static function config_redis(R $r) : \void {
		$r->setOption(R::OPT_SERIALIZER, R::SERIALIZER_PHP);
		$r->select(REDIS_DB_CACHE);
	}

	/**
	 * Test to see if a key is in the cache
	 */
	public static function test(\string $key) : \bool {
		$key = self::key_name($key);
		return self::redis()->exists($key);
	}

	/**
	 * Get the value of a key from the cache, returns
	 * null if the key doesn't exist
	 */
	public static function get(\string $key) : \mixed {
		$key = self::key_name($key);
		return self::redis()->get($key);
	}

	/**
	 * Tries to get a value out of the cache, if it doesn't exist,
	 * the given callable is executed and the key is set to that value,
	 * which is then returned.
	 *
	 * Example:
	 *
	 *	$val = Cache::get_or_set('mykey', function () {
	 *		$data = complex_calculation();
	 *		return $data;
	 *	});
	 *
	 */
	public static function get_or_set(\string $key, \callable $fn, \int $expire = Cache::DEFAULT_EXPIRE, array $tags = []) : \mixed {
		if (self::test($key)) {
			return self::get($key);
		} else {
			$val = call_user_func($fn);
			self::set($key, $val, $expire, $tags);
			return $val;
		}
	}

	/**
	 * Set a value for a key in the cache with $tags that expires after $expire seconds
	 *
	 * An expire value <= 0 means no expiration.
	 */
	public static function set(\string $key, \mixed $value, \int $expire = Cache::DEFAULT_EXPIRE, array $tags = []) : \void {
		$key = self::key_name($key);
		self::redis()->set($key, $value, $expire);
		// If expire is already over (so < 1) then don't bother adding it to
		// the tags
		if (self::redis()->exists($key)) {
			self::add_tags($key, $tags);
		}
	}

	/**
	 * Set a value for a key in the cache with $tags that expires at $expire
	 *
	 * $expire is either an integer representing the unix timestamp, or a
	 * DateTime object instance.
	 */
	public static function set_until(\string $key, \mixed $value, \mixed $expire, array $tags = []) : \void {
		$key = self::key_name($key);

		if ($expire instanceof \DateTime) {
			$expire = (int)$expire->date('U');
		}

		assert(is_numeric($expire));

		self::redis()->set($key, $value);
		self::redis()->expireat($key, $expire);
		// If expire is already over then don't bother adding it to
		// the tags
		if (self::redis()->exists($key)) {
			self::add_tags($key, $tags);
		}
	}

	/**
	 * Remove a value from the cache
	 */
	public static function remove(\string $key) : \void {
		$key = self::key_name($key);
		self::redis()->del($key);
	}

	/**
	 * Deletes all the keys in the given tags. This will only affect
	 * the given tags and will unconditionally delete the member
	 * keys.
	 */
	public static function delete_tags(...) : \void {
		$args = func_get_args();
		$tags = array_map(function (\string $t) : \string { return self::tag_name($t); }, $args);
		// Get the members of the tags
		$members = self::redis()->sunion($tags);
		// The data is serialized, so we need to unserialize it here
		$members = array_map(function ($m) { return unserialize($m); }, $members);
		// Delete in a transaction
		self::redis_transaction(function ($r) use ($tags, $members) {
			// Delete the members
			$r->del($members);
			// Delete the members from each tag
			foreach ($tags as $tag) {
				$r->srem($tag, $members);
			}
		});
	}

	private static function add_tags(\string $key, Traversable $tags) : \void {
		// Add the key to a set for each tag.
		foreach ($tags as $tag) {
			$tag = self::tag_name($tag);
			self::redis()->sadd($tag, $key);
		}
	}

	// Returns the name of the key that is used to store in redis
	private static function key_name(\string $key) : \string {
		return 'key:0:'.$key;
	}

	// Returns the key name for the set that this tag represents
	private static function tag_name(\string $tag) : \string {
		return 'tag:0:'.$tag;
	}
}
