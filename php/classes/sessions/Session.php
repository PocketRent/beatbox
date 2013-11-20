<?hh

namespace beatbox;

use Map;

class Session {
	use Settings;

	const EXPIRE = 1800;

	const NAME = 'PR';

	private static ?self $inst = null;

	private static \string $id;

	protected static function getTableName() : \string {
		return 'session';
	}

	protected function getID() : \string {
		return self::$id;
	}

	/**
	 * Start the session if needed
	 */
	protected static function start(\bool $force = false) : Session {
		if(!self::$inst) {
			if($force || !empty($_COOKIE[self::NAME])) {
				self::$inst = new self;
				self::$id = !empty($_COOKIE[self::NAME]) ? $_COOKIE[self::NAME] : generate_random_token();
				$_COOKIE[self::NAME] = self::$id;
				if(!is_cli()) {
					$host = in_dev() ? null : host_domain();
					setcookie(self::NAME, self::$id, time() + self::EXPIRE, '/', $host, false, true);
				}
				register_shutdown_function([__CLASS__, 'end']);
				self::init();
			}
		}
		return self::$inst;
	}

	protected function __construct() {}
	protected function __destruct() {}

	/**
	 * Initialise a session
	 */
	protected function init() : \void {
		if(!self::$inst->hasSetting('CSRF')) {
			self::$inst->setSetting('CSRF', generate_random_token());
		}
	}

	/**
	 * Get the item out of the session for the given key
	 */
	public static function get(\string $key) : \mixed {
		if(self::start($key === 'CSRF') && self::exists($key)) {
			return self::$inst->getSetting($key);
		}
		return null;
	}

	/**
	 * Check if there is a value set for the key in the session
	 */
	public static function exists(\string $key) : \bool {
		return self::start(false) && self::$inst->hasSetting($key);
	}

	/**
	 * Set the session value for the given key
	 */
	public static function set(\string $key, \mixed $value) : \void {
		if($key === 'CSRF') {
			throw new \InvalidArgumentException('Unable to set CSRF key.');
		}
		self::start(true) && self::$inst->setSetting($key, $value);
	}

	/**
	 * Clear the value from the session for the given key
	 */
	public static function clear(\string $key) : \void {
		self::start(false) && self::$inst->clearSetting($key);
	}

	/**
	 * Close and write the session
	 */
	public static function end() : \void {
		if(self::$inst) {
			self::$inst->endSettings();
			self::redis()->expire('session:' . self::$id, self::EXPIRE);
		}
	}

	/**
	 * Completely reset the session
	 */
	public static function reset() : \void {
		self::end();
		self::$inst = null;
		$_COOKIE[self::NAME] = null;
	}
}
