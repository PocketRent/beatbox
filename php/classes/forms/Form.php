<?hh

use beatbox;

type DataSource = array<\string,\mixed>;

class :bb:form extends :bb:base implements beatbox\FragmentCallback {
	attribute
		:form,
		var validator,
		var handler;

	protected function compose() :form {
		return <form>
			{$this->getChildren()}
			<bb:form:csrf />
		</form>;
	}

	public function forFragment(\Indexish<int, string> $url, \string $fragment) : :x:base {
		if(!$this->getAttribute('method')) {
			$this->setAttribute('method', 'post');
		}
		if($url[0] == '/') {
			$base = '/';
		} else {
			$base = implode('/', array_map(fun('rawurlencode'), $url));
		}
		if(!$this->getAttribute('action')) {
			$action = $base . '?fragments=' . rawurlencode($fragment);
			$this->setAttribute('action', $action);
		}
		if(is_get()) {
			$data = session_get('form.' . $this->getAttribute('action') . '.data');
			if($data && $data instanceof \Indexish) {
				// UNSAFE
				$this->loadData($data);
				$this->validate();
				session_clear('form.' . $this->getAttribute('action') . '.data');
			}
		} else {
			$this->loadData($_POST + $_FILES, true);
			if(!$this->validate()) {
				if(!is_ajax()) {
					// Save session data
					session_set('form.' . $this->getAttribute('action') . '.data',
								$_POST + $_FILES);
					// Redirect back
					redirect_back($base);
				} else {
					// Fall back to the return
				}
			} else {
				$handler = $this->getAttribute('handler');
				if(!$handler || !is_callable($handler)) {
					throw new beatbox\errors\Exception(
						'No handler provided or non-callable handler provided.'
					);
				}
				return call_user_func($handler, $this, $this->getData());
			}
		}
		return $this;
	}

	protected function getFields(?:x:base $base = null) : Continuation {
		if(!$base) {
			$base = $this;
		}
		foreach($base->getChildren() as $field) {
			if($field instanceof :bb:form:field) {
				yield $field;
			}
			if($field instanceof :x:base) {
				foreach($this->getFields($field) as $f) yield $f;
			}
		}
	}

	protected static function add_to_map(Map<string,mixed> $base, string $name,
											mixed $value) : void {
		$matches = [];
		if(preg_match('#^(.+?)\[(.*?)\](.*)$#', $name, $matches)) {
			if(!$matches[2]) {
				$matchVec = $base->get($matches[1]);
				if($matchVec == null) {
					$matchVec = Vector {};
					$base[$matches[1]] = $matchVec;
				}
				invariant($matchVec instanceof Vector, '$matches should be a Vector');
				$matchVec->add($value);
			} else {
				$matchMap = $base->get($matches[1]);
				if($matchMap == null) {
					$matchMap = Map {};
					$base[$matches[1]] = $matchMap;
				}
				invariant($matchMap instanceof Map, '$matches should be a Map');
				self::add_to_map($matchMap, $matches[2] . $matches[3], $value);
			}
		} else {
			$base[$name] = $value;
		}
	}

	protected function getData() : Map {
		$data = Map {};
		foreach($this->getFields() as $field) {
			if(!$field->getAttribute('name')) {
				continue;
			}
			self::add_to_map($data, $field->getAttribute('name'), $field->getValue());
		}
		return $data;
	}

	protected static array $load_count = [];

	protected static function get_value(array<string,mixed> $data, string $name,
										string $base) : mixed {
		$matches = [];
		if(!$name) {
			return $data;
		} else if(preg_match('#^(.+?)\[(.*?)\](.*)$#', $name, $matches)) {
			if(!$matches[2]) {
				$index = isset(self::$load_count[$base . $matches[1]]) ?
					self::$load_count[$base . $matches[1]] + 1 :
					0;
				self::$load_count[$base . $matches[1]] = $index;

				if (isset($data[$matches[1]])) {
					$field = $data[$matches[1]];
					if (is_array($field)) {
						if(isset($field[$index])) {
							return $field[$index];
						}
					}
				}
			} else if(isset($data[$matches[1]])) {
				$field = $data[$matches[1]];
				if (is_array($field)) {
					$base .= "$matches[1]-";
					return self::get_value($field, $matches[2].$matches[3], $base);
				}
			}
		} else if(isset($data[$name])) {
			return $data[$name];
		}
		return null;
	}

	public function loadData(array<string, mixed> $data, bool $empty = false) : :bb:form {
		self::$load_count = [];
		foreach($this->getFields() as $field) {
			$name = $field->getAttribute('name');
			if(!$name) {
				if($empty) {
					$field->setValue(null);
				}
				continue;
			}
			$value = self::get_value($data, $name, '');
			if($value !== null) {
				$field->setValue($value);
			} else if($empty) {
				$field->setValue(null);
			}
		}
		return $this;
	}

	public function validate() : bool {
		$errors = Map {};
		$fields = Map {};
		foreach($this->getFields() as $field) {
			if(!($name = $field->getAttribute('name'))) continue;
			self::add_to_map($fields, $name, $field);
			list($valid, $error) = $field->validate();
			if(!$valid) {
				$errors[$name] = $error;
			}
		}
		$valid = $errors->count() == 0;
		$validator = $this->getAttribute('validator');
		if(is_callable($validator)) {
			$valid = call_user_func($validator, $fields, $errors) && $valid;
		}
		return $valid;
	}
}
