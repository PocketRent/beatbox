<?hh

/**
 * Miscellaneous utility functions
 */

/**
 * A variant of the standard `join`/`implode` function that works
 * for any traversable, not just arrays
 */
function pr_join(string $delimiter, Traversable $trav) : string {
	$add_delim = false;
	$str = "";
	foreach ($trav as $elem) {
		if ($add_delim)
			$str .= $delimiter;
		$str .= $elem;
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
			$same &= $check[$i] === null;
		} else {
			$same &= $token[$i] === $check[$i];
		}
	}

	if($nop != $stop) {
		return false;
	}

	return (bool)$same;
}

/**
 * Returns if $left is strictly less than $right
 */
function compare_items<T>(T $left, T $right) : bool {
	if ($left instanceof \beatbox\orm\DateTimeType) {
		return $left->lt($right);
	}
	return item_difference($left, $right) < 0;
}

/**
 * Calculates the difference between two items
 */
function item_difference<T>(T $left, T $right) : int {
	if(is_string($left) && !is_numeric($left)) {
		return strcmp($left, $right);
	}
	if(is_scalar($left)) {
		return $left - $right;
	}
	if($left instanceof DateTime) {
		$diff = $right->diff($left);
		if($diff->invert == -1) {
			$mult = -1;
		} else {
			$mult = 1;
		}
		return $diff->days * $mult;
	}
	return 0;
}

/**
 * Gets the mime type of a file
 */
function get_mime_type(string $filename) : string {
	if(class_exists('finfo', false)) {
		return (new finfo(FILEINFO_MIME))->file($filename);
	} else {
		$filename = escapeshellarg($filename);
		return trim(`file -b --mime-type $filename`);
	}
}

/**
 * Gets the domain from the HTTP_HOST server variable
 */
function host_domain() : \string {
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
