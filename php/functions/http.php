<?hh

define('HTTP_URL_REPLACE', 0x000);
define('HTTP_URL_JOIN_PATH', 0x001);
define('HTTP_URL_JOIN_QUERY', 0x002);
define('HTTP_URL_STRIP_USER', 0x004);
define('HTTP_URL_STRIP_PASS', 0x008);
define('HTTP_URL_STRIP_AUTH', (HTTP_URL_STRIP_USER|HTTP_URL_STRIP_PASS));
define('HTTP_URL_STRIP_PORT', 0x020);
define('HTTP_URL_STRIP_PATH', 0x040);
define('HTTP_URL_STRIP_QUERY', 0x080);
define('HTTP_URL_STRIP_FRAGMENT', 0x100);
define('HTTP_URL_STRIP_ALL', HTTP_URL_STRIP_AUTH | HTTP_URL_STRIP_PORT | HTTP_URL_STRIP_PATH | HTTP_URL_STRIP_QUERY | HTTP_URL_STRIP_FRAGMENT);
define('HTTP_URL_FROM_ENV', 0x1000);
define('HTTP_URL_SANITIZE_PATH', 0x2000);

function http_build_url(mixed $url = null, mixed $parts = null, int $flags = HTTP_URL_FROM_ENV, array &$ret_array = null) : string {
	if($flags & HTTP_URL_FROM_ENV) {
		$url = http_build_url(_http_url_from_env(), $url, $flags ^ HTTP_URL_FROM_ENV, $ret_array);
	}

	if(!is_array($url)) {
		$url = parse_url($url);
	}
	if($parts === null) {
		$parts = [];
	}
	if(!is_array($parts)) {
		$parts = parse_url($parts);
	}

	$new_url = array();
	if(!($flags & HTTP_URL_STRIP_PORT)) {
		$new_url['port'] = array_key_exists('port', $parts) ? $parts['port'] : (array_key_exists('port', $url) ? $url['port'] : 0);
	}

	$copies = Map<string, int> {
		'user' => HTTP_URL_STRIP_USER,
		'pass' => HTTP_URL_STRIP_PASS,
		'scheme' => 0,
		'host' => 0,
		'fragment' => HTTP_URL_STRIP_FRAGMENT,
	};

	if($flags & HTTP_URL_JOIN_PATH && !empty($url['path']) && !empty($parts['path']) && $parts['path'] != '/') {
		if(!($flags & HTTP_URL_STRIP_PATH)) {
			$new_url['path'] = $url['path'];
			if(substr($new_url['path'], -1) != '/') {
				$new_url['path'] = dirname($new_url['path']) . '/';
			}
			$new_url['path'] .= $parts['path'];
		}
	} else {
		$copies['path'] = HTTP_URL_STRIP_PATH;
	}

	if($flags & HTTP_URL_JOIN_QUERY && !empty($url['query']) && !empty($parts['query'])) {
		if(!($flags & HTTP_URL_STRIP_QUERY)) {
			parse_str($url['query'], $params);
			parse_str($parts['query'], $params);
			$new_url['query'] = http_build_query($params);
		}
	} else {
		$copies['query'] = HTTP_URL_STRIP_QUERY;
	}

	foreach($copies as $name => $const) {
		if(!($flags & $const)) {
			$new_url[$name] = array_key_exists($name, $parts) ? $parts[$name] : (array_key_exists($name, $url) ? $url[$name] : null);
		}
	}

	if($new_url['scheme'] === null) {
		$new_url['scheme'] = 'http';
	}
	if($new_url['host'] === null) {
		$new_url['host'] = 'localhost';
	}
	if(!isset($new_url['path']) || $new_url['path'] === '') {
		$new_url['path'] = '/';
	} elseif(isset($new_url['path'][0]) && $new_url['path'][0] != '/') {
		$new_url['path'] = '/' . $new_url['path'];
	}

	if($flags & HTTP_URL_SANITIZE_PATH && isset($new_url['path'][0]) && ($new_url['path'][0] != '/' || isset($new_url['path'][1]))) {
		$path_parts = explode('/', $new_url['path']);
		$new_parts = [];
		if($path_parts[0] === '') {
			$new_parts[] = $path_parts[0];
			unset($path_parts[0]);
		}
		reset($path_parts);
		$append = false;
		while(list(, $part) = each($path_parts)) {
			// Handle a double slash
			if($part === '') {
				$append = true;
				continue;
			}
			if($part == '.') {
				$append = true;
				continue;
			}
			if($part == '..') {
				array_pop($new_parts);
				$append = true;
				continue;
			}
			$new_parts[] = $part;
			$append = false;
		}
		$new_url['path'] = implode('/', $new_parts);
		if($append) {
			$new_url['path'] .= '/';
		}
	}

	if(!empty($new_url['port'])) {
		if(($new_url['port'] == 80 && $new_url['scheme'] == 'http') ||
			($new_url['port'] == 443 && $new_url['scheme'] == 'https')) {
			$new_url['port'] = 0;
		}
	}

	if(!is_null($ret_array)) {
		$ret_array = $new_url;
	}

	return _http_url_to_string($new_url);
}

function _http_url_to_string(array $url) : string {
	$str = '';
	if(isset($url['scheme']) && $url['scheme'] !== '') {
		$str = $url['scheme'] . '://';
	} else {
		$str = '//';
	}

	if(isset($url['user']) && $url['user'] !== '') {
		$str .= $url['user'];
		if(isset($url['pass']) && $url['pass'] !== '') {
			$str .= ':' . $url['pass'];
		}
		$str .= '@';
	}

	if(isset($url['host']) && $url['host'] !== '') {
		$str .= $url['host'];
	} else {
		$str .= 'localhost';
	}

	if(!empty($url['port'])) {
		$str .= sprintf(':%d', $url['port']);
	}

	if(isset($url['path']) && $url['path'] !== '') {
		$str .= $url['path'];
	}

	if(isset($url['query']) && $url['query'] !== '') {
		$str .= '?' . $url['query'];
	}

	if(isset($url['fragment']) && $url['fragment'] !== '') {
		$str .= '#' . $url['fragment'];
	}

	return $str;
}

function _http_url_from_env() : array {
	$url = [];
	// Port
	if(isset($_SERVER['SERVER_PORT'])) {
		$url['port'] = (int)$_SERVER['SERVER_PORT'];
	}

	// Scheme
	if(isset($_SERVER['HTTPS']) && strcasecmp($_SERVER['HTTPS'], 'ON') == 0) {
		$url['scheme'] = 'https';
	} else {
		$url['scheme'] = 'http';
	}

	// Host
	if(!empty($_SERVER['HTTP_HOST'])) {
		$url['host'] = $_SERVER['HTTP_HOST'];
	} elseif(!empty($_SERVER['SERVER_NAME'])) {
		$url['host'] = $_SERVER['SERVER_NAME'];
	} elseif(!empty($_SERVER['SERVER_ADDR'])) {
		$url['host'] = $_SERVER['SERVER_ADDR'];
	} else {
		$url['host'] = 'localhost';
	}

	// Path
	if(!empty($_SERVER['SCRIPT_URL'])) {
		$url['path'] = $_SERVER['SCRIPT_URL'];
	}

	// Query
	if(!empty($_SERVER['QUERY_STRING'])) {
		$url['query'] = $_SERVER['QUERY_STRING'];
	}

	return $url;
}
