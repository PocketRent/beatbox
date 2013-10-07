<?php

namespace beatbox\test;

trait Redis {
	use \beatbox\Redis;

	protected static function config_redis(\Redis $inst) {}

	protected function tearDown() {
		self::redis()->flushdb();
	}
}
