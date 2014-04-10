<?hh

const int T_XHP_LABEL = 382;
const int T_SHAPE = 402;
const int T_NEWTYPE = 403;
const int T_TYPE = 405;
const int T_GROUP = 423;

namespace beatbox\utils;

type SymbolMap = shape(
	'class' => array<string, string>,
	'function' => array<string, string>,
	'constant' => array<string, string>,
	'type' => array<string, string>
);

function empty_symbol_map() : SymbolMap {
	return shape(
		'class' => [],
		'function' => [],
		'constant' => [],
		'type' => []
	);
}

function common_base_dir(Set<string> $dirs) : string {
	assert($dirs->count() > 0);
	$dirs = $dirs->toImmVector();
	if ($dirs->count() == 1) return substr($dirs[0],0,-1);
	$base = '';

	$i = 0;
	while (true) {
		if (strlen($dirs[0]) < $i)
			break;
		$char = $dirs[0][$i];
		$match = true;
		foreach($dirs as $dir) {
			if (strlen($dir) < $i) {
				$match = false;
				break;
			}
			if ($dir[$i] != $char) {
				$match = false;
				break;
			}
		}

		if ($match) {
			$base .= $char;
		} else {
			break;
		}

		$i++;
	}


	$slash_pos = strrpos($base, '/');
	$base = substr($base, 0, $slash_pos);

	return $base;
}

function build_symbol_map(Traversable<string> $directories) : (string, SymbolMap) {
	$map = shape(
		'class' => [],
		'function' => [],
		'constant' => [],
		'type' => []
	);

	$dirs = Set {};
	foreach ($directories as $dir) {
		$dirs[] = realpath($dir).'/';
	}

	$stripped = Set {};
	foreach ($dirs as $d1) {
		$add = $d1;
		foreach ($dirs as $d2) {
			if ($d1 == $d2) continue;
			$len = min(strlen($d1), strlen($d2));
			if (strncmp($d1, $d2, $len) == 0) {
				if (strlen($d1) > strlen($d2)) {
					$add = $d2;
				}
			}
		}
		if ($add) $stripped[] = $add;
	}

	$dirs = $stripped;

	$base = common_base_dir($dirs).'/';
	$cwd = getcwd().'/';

	$cwd_len = strlen($cwd);


	$relative = false;
	if ($cwd == $base) {
		$base = "";
		$relative = true;
	} else if (strncmp($base, $cwd, $cwd_len) == 0) {
		$base = substr($base, $cwd_len);
		$relative = true;
	}

	$base_len = strlen($base);

	if ($relative) {
		$dirs = $dirs->map(function ($d) use ($cwd_len) {
			return substr($d, $cwd_len % strlen($d));
		});
	}

	foreach ($dirs as $dir) {
		$map = array_merge_recursive($map, get_symbols_from_dir($dir));
	}

	$strip_len = $base_len;
	if ($relative)
		$strip_len += $cwd_len;
	foreach ($map['class'] as $cls => $file) {
		if (is_array($file)) $file = array_pop($file);
		$map['class'][$cls] = substr(realpath($file), $strip_len);
	}

	foreach ($map['function'] as $fn => $file) {
		if (is_array($file)) $file = array_pop($file);
		$map['function'][$fn] = substr(realpath($file), $strip_len);
	}

	foreach ($map['type'] as $type => $file) {
		if (is_array($file)) $file = array_pop($file);
		$map['type'][$type] = substr(realpath($file), $strip_len);
	}

	foreach ($map['constant'] as $const => $file) {
		if (is_array($file)) $file = array_pop($file);
		$map['constant'][$const] = substr(realpath($file), $strip_len);
	}


	return tuple($base, $map);
}

function get_symbols_from_dir(string $dirname) : SymbolMap {
	$map = empty_symbol_map();
	if (is_dir($dirname)) {
		if ($handle = opendir($dirname)) {
			while (false != ($entry = readdir($handle))) {
				if ($entry[0] == '.' && $entry != './')
					continue;
				$entry = $dirname.'/'.$entry;
				if (is_dir($entry)) {
					$map = array_merge_recursive($map, get_symbols_from_dir($entry));
				} else if (substr($entry, -4) == '.php') {
					$map = array_merge_recursive($map, get_symbols_from_file($entry));
				}
			}
			closedir($handle);
		}
	} else {
		$map = get_symbols_from_file($dirname);
	}

	return $map;
}

function get_symbols_from_file(string $filename) : SymbolMap {
	$contents = file_get_contents($filename);
	if (!$contents) return empty_symbol_map();
	return get_symbols_from_source($filename, $contents);
}

function get_symbols_from_source(string $filename, string $source) : SymbolMap {
	$parser = new SymbolParser($filename, token_get_all($source));

	return $parser->parseSymbols();
}

class SymbolParser {
	private string $filename;
	private array<mixed> $tokens;
	private int $pos = 0;
	private $symbol_map = shape(
		'class' => [],
		'function' => [],
		'constant' => [],
		'type' => []
	);

	private Vector<string> $namespace_scope = Vector {};

	public function __construct(string $filename, array<mixed> $tokens) {
		$this->filename = $filename;
		$this->tokens = $tokens;
	}

	public function parseSymbols() : SymbolMap {
		while ($this->pos < count($this->tokens)) {
			$this->skipTokens();
			$token = $this->getToken();

			switch ($token) {
			case T_ABSTRACT:
			case T_FINAL:
				$this->bump();
				$this->skipWhitespace();
				// FALLTHROUGH
			case T_CLASS:
			case T_INTERFACE:
			case T_TRAIT:
				$this->bump();
				$name = $this->parseFQName();
				$this->addClass($name);
				$this->skipBlock();
				break;
			case T_NAMESPACE:
				$this->parseNamespace();
				break;
			case T_FUNCTION:
				$this->bump();
				if ($this->getToken() == T_STRING) {
					$name = $this->parseFQName();
					$this->addFunction($name);
					$this->skipBlock();
				}
				break;
			case T_TYPE:
			case T_NEWTYPE:
				$this->bump();
				$name = $this->parseFQName();
				$this->addType($name);
				$this->expectChar('=');
				$this->skipType();
				break;
			case T_CONST:
				$this->bump();
				$this->skipType();
				$name = $this->parseFQName();
				$this->addConst($name);
				break;
			case T_STRING:
				if ($this->getTokenValue() == 'define') {
					$this->bump();
					$this->expectChar('(');
					$str = $this->getTokenValue();
					$name = substr($str, 1, -1);
					$this->addConst($name);
				}
				// FALLTHROUGH
			default:
				$this->bump();
			}
		}

		return $this->symbol_map;
	}

	private function parseFQName() : string {
		$name = $this->parseName();
		return $this->qualify($name);
	}

	private function parseNamespace() {
		$this->expect(T_NAMESPACE);
		$ns = '';
		while ($this->pos < count($this->tokens)) {
			if ($this->getToken() == T_NS_SEPARATOR) {
				if (strlen($ns) > 0)
					$ns .= '\\';
				$this->bump();
			} else if ($this->getToken() == T_STRING) {
				$ns .= $this->parseName();
			} else {
				if ($this->getTokenValue() == ';' && $this->namespace_scope->count() > 0) {
					$this->namespace_scope->pop();
				}
				break;
			}
		}
		if ($ns != '') {
			$this->namespace_scope->add($ns);
		}
	}

	private function parseName() : string {
		$start_token = $this->getToken();
		if ($start_token == T_XHP_LABEL) {
			$element = $this->getTokenValue();
			$this->bump();
			$this->skipGenerics();
			return 'xhp_'.str_replace(array(':', '-'), array('__', '_'), $element);
		} else if ($start_token == T_STRING) {
			$name = $this->getTokenValue();
			$this->bump();
			$this->skipGenerics();
			return $name;
		} else if ($start_token == T_GROUP) {
			$name = $this->getTokenValue();
			$this->bump();
			$this->skipGenerics();
			return $name;
		} else {
			throw new \Exception("Parse Error: Expected 'T_XHP_LABEL' or 'T_STRING', got '".
				token_name($start_token)."' in '".$this->filename."'");
		}
	}

	private function addClass(string $name) {
		$this->symbol_map['class'][strtolower($name)] = $this->filename;
	}

	private function addFunction(string $name) {
		$this->symbol_map['function'][strtolower($name)] = $this->filename;
	}

	private function addType(string $name) {
		$this->symbol_map['type'][strtolower($name)] = $this->filename;
	}

	private function addConst(string $name) {
		$this->symbol_map['constant'][$name] = $this->filename;
	}

	private function qualify(string $name) : string {
		if ($this->namespace_scope->count() == 0)
			return $name;
		else
			return implode('\\', $this->namespace_scope).'\\'.$name;
	}

	private function getToken() : int {
		$this->skipWhitespace();
		if ($this->pos >= count($this->tokens))
			return -1;
		$token = $this->tokens[$this->pos];
		if (is_array($token)) {
			return $token[0];
		} else {
			return -1;
		}
	}

	private function getTokenValue() : string {
		$this->skipWhitespace();
		if ($this->pos >= count($this->tokens))
			return "";
		$token = $this->tokens[$this->pos];
		if (is_array($token)) {
			return $token[1];
		} else {
			return (string)$token;
		}
	}

	private function expect(int $token) {
		if ($this->getToken() == $token) {
			$this->bump();
		} else {
			$expect = token_name($token);
			$actual = token_name($this->getToken());
			throw new \Exception("Parse Error: Expected '$expect', got '$actual'");
		}
	}

	private function expectChar(string $token) {
		if ($this->getTokenValue() == $token) {
			$this->bump();
		} else {
			$expect = $token;
			$actual = $this->getTokenValue();
			throw new \Exception("Parse Error: Expected '$expect', got '$actual'");
		}
	}

	private function bump() {
		$this->pos++;
	}

	private function skipWhitespace() {
		while ($this->pos < count($this->tokens)) {
			$token = $this->tokens[$this->pos];
			if (is_array($token)) {
				if ($token[0] != T_WHITESPACE)
					return;
			} else {
				return;
			}
			$this->pos++;
		}
	}

	private function skipBlock(string $from = '{', string $to = '}') {
		while ($this->pos < count($this->tokens) && $this->getTokenValue() != $from){
			$this->bump();
		}
		$scopes = 1;
		$this->bump();
		while ($scopes > 0) {
			if ($this->getTokenValue() == $from) {
				$scopes++;
			} else if ($this->getTokenValue() == $to) {
				$scopes--;
			}
			$this->bump();
		}
	}

	private function skipType(): void {
		switch ($this->getToken()) {
		case T_XHP_LABEL:
		case T_CALLABLE:
			$this->bump();
			break;
		case T_SHAPE:
			$this->skipBlock('(', ')');
			break;
		case T_ARRAY:
			$this->bump();
			$this->skipGenerics();
			break;
		case T_STRING:
			$this->bump();
			if ($this->getToken() == T_NS_SEPARATOR) {
				$this->skipType();
			}
			break;
		case T_NS_SEPARATOR:
			$this->bump();
			$this->skipType();
			break;
		default:
			switch ($this->getTokenValue()) {
			case '?':
			case '@':
				$this->bump();
				$this->skipType();
				break;
			case '(':
				$this->skipBlock('(', ')');
				break;
			}
		}
	}

	private function skipGenerics(): void {
		if ($this->getTokenValue() == '<')
			$this->skipBlock('<', '>');
	}

	private function skipTokens() : void {
		while ($this->pos < count($this->tokens)) {
			$token = $this->tokens[$this->pos];
			if (is_array($token)) {
				switch ($token[0]) {
				case T_ABSTRACT:
				case T_CLASS:
				case T_CONST:
				case T_FINAL:
				case T_FUNCTION:
				case T_INTERFACE:
				case T_NAMESPACE:
				case T_NEWTYPE:
				case T_TRAIT:
				case T_TYPE:
				case T_STRING:
					return;
				}
			} else if ($token == '}') {
				if ($this->namespace_scope->count() > 0)
					$this->namespace_scope->pop();
			}
			$this->pos++;
		}
	}
}
