<?hh

namespace beatbox\errors;

use HH\Traversable;

class PHP {
	private static $fatal_errors = \Set<\int>{
		E_ERROR,
		E_PARSE,
		E_CORE_ERROR,
		E_COMPILE_ERROR,
		E_USER_ERROR
	};

	/**
	 * Handler for a PHP error
	 */
	public static function errors(\int $number, \string $message, \string $file, \int $line) {
		if(error_reporting() == 0 && !self::$fatal_errors->contains($number)) {
			return false;
		}
		$stack = debug_backtrace();
		$message = trim($message);
		send_event('error:' . $number, $number, $message, $file, $line, $stack);

		if(in_dev()) {
			$e = new HTTP_Exception(null, 500);
			$e->sendToBrowser();

			if(!is_cli()) {
				echo "<pre>";
			}
			echo self::number_to_error($number);
			echo ": $message in $file:$line\n";
			$bt = self::pretty_backtrace($stack);
			if(!is_cli()) {
				echo htmlentities($bt);
				echo "</pre>";
			} else {
				echo $bt, "\n";
			}
			die();
		} else {
			if(self::$fatal_errors->contains($number)) {
				http_error(500);
			}
		}
		return true;
	}

	private static function number_to_error(\int $num) : \string {
		switch($num) {
			case E_ERROR: return "E_ERROR";
			case E_WARNING: return "E_WARNING";
			case E_PARSE: return "E_PARSE";
			case E_NOTICE: return "E_NOTICE";
			case E_CORE_ERROR: return "E_CORE_ERROR";
			case E_CORE_WARNING: return "E_CORE_WARNING";
			case E_COMPILE_ERROR: return "E_COMPILE_ERROR";
			case E_COMPILE_WARNING: return "E_COMPILE_WARNING";
			case E_USER_ERROR: return "E_USER_ERROR";
			case E_USER_WARNING: return "E_USER_WARNING";
			case E_USER_NOTICE: return "E_USER_NOTICE";
			case E_STRICT: return "E_STRICT";
			case E_RECOVERABLE_ERROR: return "E_RECOVERABLE_ERROR";
			case E_DEPRECATED: return "E_DEPRECATED";
			case E_USER_DEPRECATED: return "E_USER_DEPRECATED";
			case E_ALL: return "E_ALL";
			default: return "ERROR";
		}
	}

	private static function pretty_backtrace($stack) : \string {
		// pop off the first item, as it's the call to the error handler
		array_shift($stack);
		$i = 1;
		$bt = [];
		foreach($stack as $line) {
			$bt_line = "#$i: ";
			if(isset($line['class'])) {
				$class = $line['class'];
				if(is_a($class, :x:base::element2class('x:base'), true)) {
					$class = '<' . :x:base::class2element($class) . '>';
				}
				$bt_line .= "$class$line[type]";
			}
			$bt_line .= "$line[function](";
			$bt_line .= self::pretty_args($line['args']);
			$bt_line .= "); in ";
			if(isset($line['file'])) {
				$bt_line .= "$line[file]:$line[line]";
			} else {
				$bt_line .= "unknown";
			}
			$bt[] = $bt_line;
			++$i;
		}
		return implode("\n", $bt);
	}

	private static function pretty_args(array $args) : \string {
		$pretty = array_map([__CLASS__, 'pretty_arg'], $args);
		return implode(', ', $pretty);
	}

	private static function pretty_arg($arg) : \string {
		switch(gettype($arg)) {
			case 'array':
				return self::pretty_array($arg);
			case 'object':
				if($arg instanceof Traversable) {
					return get_class($arg) . ' ' . self::pretty_array($arg);
				} elseif($arg instanceof :x:base) {
					$class = get_class($arg);
					return '<' . $arg::class2element($class) . '>';
				}
				return get_class($arg);
			case 'boolean':
				return $arg ? 'TRUE' : 'FALSE';
			case 'string':
				return '"' . $arg . '"';
			case 'NULL':
				return 'NULL';
			case 'integer':
			case 'double':
				return $arg;
			default:
				var_dump(gettype($arg));
				return $arg;
		}
	}

	private static function pretty_array(Traversable $arg) : \string {
		$items = [];
		$expected = 0;
		foreach($arg as $key => $val) {
			if(is_int($key) && $key === $expected) {
				$items[] = self::pretty_arg($val);
				++$expected;
			} else {
				$items[] = "$key => " . self::pretty_arg($val);
				if(is_int($key)) {
					$expected = $key + 1;
				} else {
					$expected = '';
				}
			}
		}
		return '[' . implode(', ', $items) . ']';
	}

	/**
	 * Handler for uncaught exceptions
	 */
	public static function exceptions(\Exception $exception) : \bool {
		$message = trim($exception->getMessage());
		$file = $exception->getFile();
		$line = $exception->getLine();
		$stack = $exception->getTrace();
		$event = 'exception:' . $exception->getCode();
		if($exception instanceof Exception) {
			$event = $exception->getEventPrefix() . $event;
		}
		send_event($event, $exception->getCode(), $message, $file, $line, $exception, $stack);
		if($exception instanceof HTTP_Exception) {
			$code = $exception->getBaseCode();
			$exception->sendToBrowser();
			if($code >= 200 && $code <= 399) {
				return true;
			}
		} else {
			$e = new HTTP_Exception(null, 500);
			$e->sendToBrowser();
		}
		if(in_dev()) {
			if(!is_cli()) {
				echo "<pre>";
			}
			$multi = false;
			do {
				$message = trim($exception->getMessage());
				$file = $exception->getFile();
				$line = $exception->getLine();
				$stack = $exception->getTrace();

				if ($multi)
					echo "\n";
				echo "Exception: \"$message\" in $file:$line\n";
				$bt = self::pretty_backtrace($stack);
				$multi = true;
			} while ($exception = $exception->getPrevious());

			if(!is_cli()) {
				echo htmlentities($bt);
				echo "</pre>";
			} else {
				echo $bt, "\n";
			}
			return true;
		} elseif($exception instanceof HTTP_Exception) {
			$code = $exception->getBaseCode();
			$path = 'error-' . $code;
			$routes = \beatbox\Router::get_routes_for_path($path)[0];
			if(!empty($routes['page'])) {
				echo \beatbox\Router::route($path);
			} elseif(file_exists(BASE_DIR . '/src/errors/' . $path . '.html')) {
				echo file_get_contents(BASE_DIR . '/src/errors/' . $path . '.html');
			} elseif(file_exists(BASE_DIR . '/src/errors/error.html')) {
				echo file_get_contents(BASE_DIR . '/src/errors/error.html');
			} else {
				echo $exception->getMessage(), "\n";
			}
			return true;
		} else {
			return self::exceptions(new HTTP_Exception(null, 500));
		}
	}
}


// Load the error handlers
set_error_handler([__NAMESPACE__ . '\PHP', 'errors']);
set_exception_handler([__NAMESPACE__ . '\PHP', 'exceptions']);
