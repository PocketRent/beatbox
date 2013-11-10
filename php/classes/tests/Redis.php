<?hh

namespace beatbox\test;

trait Redis {
	use \beatbox\Redis;

	protected static function config_redis(\Redis $inst) : \void {}

	protected function tearDown() : \void {
		self::redis()->flushdb();
	}
}
