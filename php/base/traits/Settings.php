<?php

namespace pr\base;

use Map;

trait Settings {
	use Redis;

	/**
	 * Returns the name of the table for this object. Is used as the ObjectType value.
	 */
	abstract protected static function getTableName();

	/**
	 * Returns the ID for this object. This should be unique across objects that have the same table name.
	 */
	abstract protected function getID();

	private $settings_loaded = false;
 	private $settings_data = \Map<\string> {};
	private $settings_original = \Map<\string> {};

	private $settings_key;

	protected static function config_redis(\Redis $inst) {
		$inst->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
		$inst->select(REDIS_DB_SETTINGS);
	}

	protected function loadSettings() {
		if($this->settings_loaded) return;
		$this->settings_loaded = true;
		$this->settings_key = self::getTableName() . ':' . $this->getID();
		$data = self::redis()->hgetall($this->settings_key);
		if($data) {
			$this->settings_data = Map::fromArray($data);
			$this->settings_original = Map::fromArray($data);
		}
		register_shutdown_function([$this, 'endSettings']);
	}

	public function getSetting(\string $key) {
		if($this->hasSetting($key)) {
			return $this->settings_data[$key];
		}
		return null;
	}

	public function hasSetting(\string $key) : \bool {
		$this->loadSettings();
		return isset($this->settings_data[$key]);
	}

	public function setSetting(\string $key, $value) {
		$this->loadSettings();
		$this->settings_data[$key] = $value;
	}

	public function clearSetting(\string $key) {
		$this->loadSettings();
		unset($this->settings_data[$key]);
	}

	public function endSettings() {
		if($this->settings_loaded) {
			self::redis_transaction(function(\Redis $r) {
				$set = [];
				foreach($this->settings_data as $k => $v) {
					if(!isset($this->settings_original[$k]) || $this->settings_original[$k] != $v) {
						$set[$k] = $v;
					}
				}
				if(count($set)) {
					$r->hmset($this->settings_key, $set);
				}
				$del = [$this->settings_key];
				foreach($this->settings_original->differenceByKey($this->settings_data)->keys() as $key) {
					$del[] = $key;
				}
				if(count($del) > 1) {
					call_user_func_array([$r, 'hdel'], $del);
				}
			});

			$this->settings_original = Map<\string> {};
			$this->settings_data = Map<\string> {};
			$this->settings_loaded = false;
		}
	}
}
