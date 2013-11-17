<?hh

function db_parse_array(string $delimiter, string $val): Vector<string> {
	$generator = function () : Continutaion use ($delimiter, $val) {
		$len = strlen($val);
		if ($len > 0) {
			$start = 0;

			$brace_count = 0;

			$in_string = false;

			for($i = 0; $i < $len; $i++) {
				$c = $val[$i];
				if (!$in_string && $brace_count == 0 && $c == $delimiter) {
					yield trim(substr($val, $start, ($i-$start)));
					$start = $i+1;
				}
				if ($c == '"')
					$in_string = !$in_string;

				if ($c == '{' && !$in_string) {
					$brace_count++;
				} else if ($c == '}' && !$in_string) {
					$brace_count--;
				}
				if ($c == '\\' && $in_string) $i++;
			}
			yield trim(substr($val, $start));
		}
	};

	$vec = Vector {};
	foreach ($generator() as $elem) {
		$vec->add($elem);
	}

	return $vec;
}

function db_parse_composite(string $val): Vector<string> {
	$generator = function () : Continutaion use ($val) {
		$len = strlen($val);
		if ($len > 0 && $val != '()') {
			$start = 1;

			$brace_count = 0;

			$in_string = false;

			for($i = 0; $i < $len; $i++) {
				$c = $val[$i];
				if (!$in_string && $brace_count == 1 && $c == ',') {
					yield trim(substr($val, $start, ($i-$start)));
					$start = $i+1;
				}
				if ($c == '"')
					$in_string = !$in_string;

				if ($c == '(' && !$in_string) {
					$brace_count++;
				} else if ($c == ')' && !$in_string) {
					$brace_count--;
				}
				if ($c == '\\' && $in_string) $i++;
			}
			yield trim(substr($val, $start, -1));
		}
	};

	$vec = Vector {};
	foreach ($generator() as $elem) {
		$elem = db_parse_string($elem);
		$vec->add($elem);
	}

	return $vec;
}

function db_parse_string(string $val) : string {
	if (strlen($val) < 2) return $val;
	if ($val[0] == '"') {
		// Strip off the leading/trailing '"'
		$val = substr($val, 1, -1);
		// Replace '""' with '"' (postgres escaping is weird sometimes)
		$val = preg_replace('#""#', '"', $val);
		$len = strlen($val);
		$str = "";
		for ($i = 0; $i < $len; $i++) {
			$c = $val[$i];
			if (($i+1 < $len) && $c == '\\') {
				if ($val[$i+1] == '\\') {
					$str .= '\\';
					$i++;
				}
			} else {
				$str .= $c;
			}
		}

		return $str;
	} else {
		return $val;
	}
}
