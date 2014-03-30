<?hh

/*
 * Loader functions
 *
 * These are used to load up everything required for Beatbox. They shouldn't
 * be used in production, as they do some redundant work. Instead, an entry
 * script should be generated using `tools/deploy`.
 */

require_once __DIR__.'/symbolmap.php';

function initialize_beatbox(string $conf) : void {
	date_default_timezone_set('UTC');

	if (is_readable($conf)) {
		// UNSAFE
		require_once $conf;

		$dirs = ImmSet {
			__DIR__.'/..', // Main Beatbox directory
			__DIR__.'/../../lib', // Beatbox library directory
			APPLICATION_DIR
		};

		register_autoload_map($dirs);

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
	$map['failure'] = function ($kind, $name) use ($dirs, $hash, $base, $map) {
		if ($kind != 'constant') {
			$name = strtolower($name);
		}
		// Check to see if it's in the map, if the file has an error, it won't say, so
		// try loading it manually
		if (isset($map[$kind][$name])) {
			require_once $map[$kind][$name];
		}
		if ($kind == 'class' || $kind == 'type') {
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
