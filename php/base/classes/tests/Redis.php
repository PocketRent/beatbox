<?php

namespace pr\base\test;

trait Redis {
	use \pr\base\Redis;

	protected static function config_redis(\Redis $inst) {}

	protected function tearDown() {
		self::redis()->flushdb();
	}
}
