<?hh

/**
 * Miscellaneous utility functions
 */

/**
 * A variant of the standard `join`/`implode` function that works
 * for any traversable, not just arrays
 */
function bb_join<Tv>(string $delimiter, Traversable<Tv> $trav) : string {
	$add_delim = false;
	$str = "";
	foreach ($trav as $elem) {
		if ($add_delim)
			$str .= $delimiter;
		if ($elem instanceof Awaitable)
			$elem = wait($elem);

		if (is_object($elem)) {
			if (method_exists($elem, '__toString')) {
				// UNSAFE
				$elem = $elem->__toString();
			} else {
				throw new InvalidArgumentException('bb_join expects to be able to turn all elements into strings');
			}
		}
		$str .= strval($elem);
		$add_delim = true;
	}

	return $str;
}

/**
 * Generate a random token of the given length
 */
function generate_random_token(int $length = 64) : string {
	assert($length > 0 && "Length must be greater than 0");

	$bytes = mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);

	static $str = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-';
	static $mask = 63;

	$token = '';
	for($i = 0; $i < $length; ++$i) {
		$token .= $str[ord($bytes[$i]) & $mask];
	}
	return $token;
}

/**
 * Check if the provided token matches the expected one
 *
 * This does a constant-time comparison
 */
function check_token(string $check, string $token) : bool {
	$same = true;
	$stop = strlen($check);
	$nop = strlen($token);
	for($i = 0; $i < $stop; ++$i) {
		if($i >= $nop) {
			$same = $same && ($check[$i] === null);
		} else {
			$same = $same && ($token[$i] === $check[$i]);
		}
	}

	if($nop != $stop) {
		return false;
	}

	assert(!$same || $check == $token);
	assert($same || $check != $token);

	return $same;
}

/**
 * Returns if $left is strictly less than $right
 */
function compare_items<T>(T $left, T $right) : bool {
	if ($left instanceof \beatbox\Comparable) {
		return $left->lt($right);
	}
	return (int)item_difference($left, $right) < 0;
}

/**
 * Calculates the difference between two items
 */
function item_difference<T>(T $left, T $right) : float {
	if(is_string($left) && !is_numeric($left)) {
		return (float)strcmp($left, $right);
	}
	if(is_numeric($left)) {
		$left = (float)$left;
		$right = (float)$right;
		return $left - $right;
	}
	if($left instanceof DateTime && $right instanceof DateTime) {
		$diff = $right->diff($left);
		if($diff->invert == -1) {
			$mult = -1;
		} else {
			$mult = 1;
		}
		return (float)$diff->days * $mult;
	}
	return 0.0;
}

/**
 * Gets the mime type of a file
 */
function get_mime_type(string $filename) : string {
	return (new finfo(FILEINFO_MIME_TYPE))->file($filename);
}

/**
 * Gets the domain from the HTTP_HOST server variable
 */
function host_domain() : ?string {
	if (!isset($_SERVER['HTTP_HOST'])) return null;

	$host = $_SERVER['HTTP_HOST'];
	if (($c = strrpos($host, ':'))) {
		$host = substr($host, 0, $c);
	}

	return $host;
}

/**
 * Gets the base URL
 */
function base_url() : \string {
	if(!isset($_SERVER['SCRIPT_URI'])) {
		return '';
	}
	$uri = $_SERVER['SCRIPT_URI'];
	$url = $_SERVER['SCRIPT_URL'];

	$len = -strlen($url) + 1;

	if($len) {
		$uri = substr($uri, 0, -strlen($url) + 1);
	}
	return $uri;
}

function tuple(...) {
	invariant(func_num_args() > 1, 'Tuples of one element are not allowed');
	return func_get_args();
}

function fun(string $name) {
	assert(function_exists($name));
	return $name;
}

function inst_meth<T>(T $obj, string $meth) {
	assert(method_exists($obj, $meth) || method_exists($obj, '__call'));
	return [$obj, $meth];
}

function class_meth(string $class, string $meth) {
	assert(method_exists($class, $meth) || method_exists($class, '__callStatic'));
	return [$class, $meth];
}
