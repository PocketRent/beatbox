<?hh

namespace beatbox;

class Browser {

	public static function device_type(\bool $long=false) : \mixed {
		if ($long) {
			return self::info('device_type');
		} else {
			switch(self::info('device_type')) {
			case 'mobile': return DEVICE_MOBILE;
			case 'tablet': return DEVICE_TABLET;
			case 'desktop': return DEVICE_DESKTOP;
			default: return DEVICE_DESKTOP;
			}
		}
	}

	/**
	 * Returns an array with the width and height of the screen, in that order
	 * [w, h]
	 */
	public static function size() : array {
		return [self::info('width'), self::info('height')];
	}

	public static function svg() : \bool {
		$cap = self::info('cap');
		return $cap && isset($cap['svg']);
	}

	public static function isIE8() : \bool {
		return (bool)self::info('isIE8');
	}

	public static function inline_svg() : \bool {
		$cap = self::info('cap');
		return $cap && isset($cap['inlinesvg']);
	}

	public static function info(?\string $key=null) : \mixed {
		static $info = null;
		if ($info === null) {
			if (isset($_COOKIE['ci'])) {
				$info = json_decode($_COOKIE['ci'], true);
			} else {
				$info = [];
			}
		}

		if ($key) {
			if (isset($info[$key])) {
				return $info[$key];
			} else {
				return null;
			}
		} else {
			return $info;
		}
	}

}
