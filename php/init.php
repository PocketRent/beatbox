<?hh

// Front-load the env functions so everything can use them
// to check stuff
require_once __DIR__ . '/functions/env/Env.php';

// Load the error handlers
require_once __DIR__ . '/classes/errors/Exception.php';
require_once __DIR__ . '/classes/errors/HTTP.php';
require_once __DIR__ . '/classes/errors/PHP.php';

// Load all helper functions - This shouldn't be here see issue:
//		https://github.com/facebook/hhvm/issues/2206
require_once __DIR__ . '/functions/loader.php';

// Load the, uhh, loader functions
require_once __DIR__.'/utils/loader.php';
