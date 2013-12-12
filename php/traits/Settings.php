<?hh

namespace beatbox;

use Map;

trait Settings {
	use Redis;

	/**
	 * Returns the name of the table for this object. Is used as the ObjectType value.
	 */
	abstract protected static function getTableName() : \string;

	/**
	 * Returns the ID for this object. This should be unique across objects that have the same table name.
	 */
	abstract protected function getID() : \mixed;

	private \bool $settings_loaded = false;
	private \Map<\string, \mixed> $settings_data = \Map {};
	private \Map<\string, \mixed> $settings_original = \Map {};

	private \string $settings_key;

	protected static function config_redis(\Redis $inst) : \void {
		$inst->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
		$inst->select(REDIS_DB_SETTINGS);
	}

	protected function loadSettings() : \void {
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

	public function getSetting(\string $key) : \mixed {
		if($this->hasSetting($key)) {
			return $this->settings_data[$key];
		}
		return null;
	}

	public function hasSetting(\string $key) : \bool {
		$this->loadSettings();
		return isset($this->settings_data[$key]);
	}

	public function setSetting(\string $key, \mixed $value) : \void {
		$this->loadSettings();
		$this->settings_data[$key] = $value;
	}

	public function clearSetting(\string $key) : \void {
		$this->loadSettings();
		unset($this->settings_data[$key]);
	}

	public function endSettings() : \void {
		if($this->settings_loaded) {
			self::redis_transaction(function(\Redis $r) {
				// Redis::hmset wants an array
				$set = [];
				foreach($this->settings_data as $k => $v) {
					if(!isset($this->settings_original[$k]) || $this->settings_original[$k] != $v) {
						$set[$k] = $v;
					}
				}
				if(count($set)) {
					$r->hmset($this->settings_key, $set);
				}
				// call_user_func_array wants an array
				$del = [$this->settings_key];
				foreach($this->settings_original->differenceByKey($this->settings_data)->keys() as $key) {
					$del[] = $key;
				}
				if(count($del) > 1) {
					call_user_func_array([$r, 'hdel'], $del);
				}
			});

			$this->settings_original = Map {};
			$this->settings_data = Map {};
			$this->settings_loaded = false;
		}
	}
}
