<?php

// INI
date_default_timezone_set('UTC');

// Constants
//// Permissions
define('PERM_UNKNOWN', 0);
define('PERM_PENDING', 1);
define('PERM_NOTALLOWED', 2);
define('PERM_ALLOWED', 4);
// Device types
define('DEVICE_DESKTOP', 1);
define('DEVICE_TABLET', 2);
define('DEVICE_MOBILE', 4);
define('DEVICE_ALL', DEVICE_DESKTOP | DEVICE_TABLET | DEVICE_MOBILE);

/** Redis Databases **/
// Default database, shouldn't normally be used
define('REDIS_DB_DEFAULT', 0);
// Cache database, should only be used by the
// cache implementation.
define('REDIS_DB_CACHE', 1);
// Session database
define('REDIS_DB_SETTINGS', 2);
// Tasks database
define('REDIS_DB_TASKS', 3);
// Test database
define('REDIS_DB_TEST', 15);

// Load the env-conf
require __DIR__ . '/../../../conf/conf.php';

// Load the error handlers
require __DIR__ . '/classes/errors/PHP.php';

// Load all helper functions
require __DIR__ . '/functions/loader.php';

// Load XHP
require BASE_DIR . '/xhp/init.php';
:x:base::$ENABLE_VALIDATION = in_dev();

// Load the classmap thing
require BASE_DIR . '/build/classmap';

// This registers the autoloader map, does it inside
// a function to avoid any issues from the require of
// the map file
function register_autoload_map() {
	$path = realpath(CONF_DIR . '/map.php');
	if($path) {
		require $path;
	} else {
		trigger_error("Cannot find class map", E_USER_ERROR);
	}

	$map['function'] = [];

	// fb_autoload_map doesn't play nice with syntax errors, so during development
	// using the spl autoloader is prefered. In production, fb_autoload_map is
	// faster, so we use that.
	if (in_dev()) {
		spl_autoload_register(function($name) use ($map) {
			$canon_name = strtolower($name);
			if (isset($map['class'][$canon_name])) {
				require BASE_DIR.'/'.$map['class'][$canon_name];
			} elseif (!defined('RUNNING_TEST')) {
				// Regenerate the map to try and load the new class
				$new_map = makeMap();
				if (count(array_diff_assoc($new_map['class'], $map['class'])) > 0) {
					$map = $new_map;
					if (isset($map['class'][$canon_name])) {
						require BASE_DIR.'/'.$map['class'][$canon_name];
					}
				}
			}
		});

	} else {
		fb_autoload_map($map, BASE_DIR.'/');
	}

}
register_autoload_map();

if (DEV_MODE) {
	assert_options(ASSERT_ACTIVE, 1);
	assert_options(ASSERT_BAIL, 1);
} else {
	assert_options(ASSERT_ACTIVE, 0);
}

// Fallback values for configuration values
if (!defined('ASSET_PATH')) {
	// This shouldn't really be in the document root, but it'll do for now
	define('ASSET_PATH', BASE_DOC_DIR.'/assets');
}
