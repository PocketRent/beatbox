<?hh

/*
 * Loader functions
 *
 * These are used to load up everything required for BeatBox. They shouldn't
 * be used in production, as they do some redundant work. Instead, an entry
 * script should be generated using `tools/deploy`.
 */

require_once __DIR__.'/symbolmap.php';

function add_application_dirs(...) : void {
	if (!is_array($GLOBALS['<__applicationDirs>']))
		$GLOBALS['<__applicationDirs>'] = [];
	foreach (func_get_args() as $e) {
		$GLOBALS['<__applicationDirs>'][] = $e;
	}
}

function initialize_beatbox(string $conf) : void {
	date_default_timezone_set('UTC');

	if (is_readable($conf)) {
		// UNSAFE
		require_once $conf;

		$dirs = Set {
			__DIR__.'/..', // Main BeatBox directory
			__DIR__.'/../../lib', // BeatBox library directory
		};

		if (defined('APPLICATION_DIR'))
		    $dirs->add(APPLICATION_DIR);

		if (isset($GLOBALS['<__applicationDirs>']))
			$dirs->addAll($GLOBALS['<__applicationDirs>']);
		
		register_autoload_map($dirs->toImmSet());

	} else {
		throw new Exception("Cannot include configuration file `$conf`");
	}

	:xhp::$ENABLE_VALIDATION = in_dev();

	if (DEV_MODE) {
		assert_options(ASSERT_ACTIVE, 1);
		assert_options(ASSERT_BAIL, 1);
	} else {
		assert_options(ASSERT_ACTIVE, 0);
	}

	$GLOBALS['<__inited>'] = true;
}

function register_autoload_map(ImmSet<string> $dirs) : string {
	$base = '';
	$map = [];
	if (file_exists(MAP_FILE)) {
		require MAP_FILE;
	} else {
		list($base, $map) = beatbox\utils\build_symbol_map($dirs);
		$map_file = fopen(MAP_FILE, "w");
		fprintf($map_file, "<?hh \n\n\$base = \"%s\";\n\n\$map=%s;\n", $base, var_export($map,true));
		fclose($map_file);
	}


	// Hash the map file so we know if it changes when we reload the map in the failure
	// handler. If we don't we'll end up in an infinite loop if the symbol doesn't exist.
	$hash = md5(file_get_contents(MAP_FILE));
	// UNSAFE
	$map['failure'] = function ($kind, $name, $error) use ($dirs, $hash, $base, $map) {
		if ($kind != 'constant') {
			$name = strtolower($name);
		}

		if ($error != null) {
			$file = $map[$kind][$name];
			if (substr($name, 0, 4) == 'xhp_') {
				$name = str_replace(array('__', '_'), array(':', '-'),
					       preg_replace('#^xhp_#i', '', $name));
			}
			throw new Exception(
				"Error trying to load $kind '$name' from '$file': \"$error\"\n");
		}

		if ($kind == 'class' || $kind == 'type') {
			if (strtok($name, '\\') == 'hh') {
				return false;
			}
			unlink(MAP_FILE);
			$new_hash = register_autoload_map($dirs);
			// Check the new hash against the captured one, if they're different, then
			// the autoloader should try again.
			return $new_hash != $hash;
		}


		// This is a workaround for HHVM issue #2206
		if (strpos($name, '\\')) {
			$slash_pos = strrpos($name, '\\');
			$name = substr($name, $slash_pos+1);

			if ($kind == 'constant')
				defined($name);
			return null;
		}
	};
	HH\autoload_set_paths($map, $base);

	// Return the hash to the failure function
	return $hash;
}
