<?hh

function db_parse_array(string $delimiter, string $val): Vector<string> {
	$generator = function () : \Continuation<string> use ($delimiter, $val) {
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
				} else if ($c == '"') {
					$in_string = !$in_string;
				} else if ($c == '{' && !$in_string) {
					$brace_count++;
				} else if ($c == '}' && !$in_string) {
					$brace_count--;
					assert($brace_count >= 0);
				} else if ($c == '\\' && $in_string) {
					$i++;
				}
			}
			yield trim(substr($val, $start));
			assert(!$in_string);
			assert($brace_count == 0);
		}
	};

	$vec = Vector {};
	foreach ($generator() as $elem) {
		$vec->add($elem);
	}

	return $vec;
}

function db_parse_composite(string $val): Vector<string> {
	$val = trim($val, '"');
	$generator = function () : Continuation<string> use ($val) {
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
				} else if ($c == '"') {
					$in_string = !$in_string;
				} else if ($c == '(' && !$in_string) {
					$brace_count++;
				} else if ($c == ')' && !$in_string) {
					$brace_count--;
					assert($brace_count >= 0);
				} else if ($c == '\\' && $in_string) {
					$i++;
				}
			}
			yield trim(substr($val, $start, -1));
			assert(!$in_string);
			assert($brace_count == 0);
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
		assert($val[strlen($val)-1] == '"');
		// Strip off the leading/trailing '"'
		$val = substr($val, 1, -1);
		// Replace '""' with '"' (postgres escaping is weird sometimes)
		$val = preg_replace('#""#', '"', $val) ?: "";
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

function db_parse_hstore(string $val) : Map<string, string> {
	$map = Map {};
	$val = trim($val);
	while (strlen($val) > 0) {
		// Parse out the key
		$cur_pos = 0;
		while ($cur_pos < strlen($val)) {
			$cur_pos++;
			if ($val[$cur_pos] == '"')
				break;
			if ($val[$cur_pos] == '\\')
				$cur_pos++;
		}
		$cur_pos++;

		$key = substr($val, 0, $cur_pos);
		$key = db_parse_string($key);
		// Skip over the arrow
		assert($val[$cur_pos++] == '=' && $val[$cur_pos++] == '>');
		assert($val[$cur_pos] == '"');

		// Parse out the value
		$val_start = $cur_pos;
		while ($cur_pos < strlen($val)) {
			$cur_pos++;
			if ($val[$cur_pos] == '"')
				break;
			if ($val[$cur_pos] == '\\')
				$cur_pos++;
		}
		$cur_pos++;

		$value = substr($val, $val_start, $cur_pos-$val_start);
		$value = db_parse_string($value);

		$map[$key] = $value;
		$val = trim(substr($val, $cur_pos+1));
	}

	return $map;
}
