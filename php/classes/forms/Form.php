<?php

use beatbox;

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

	public function forFragment(Traversable $url, string $fragment) : :x:base {
		if(!$this->getAttribute('method')) {
			$this->setAttribute('method', 'post');
		}
		if($url[0] == '/') {
			$base = '/';
		} else {
			$base = implode('/', array_map('rawurlencode', $url));
		}
		if(!$this->getAttribute('action')) {
			$action = $base . '?fragments=' . rawurlencode($fragment);
			$this->setAttribute('action', $action);
		}
		if(is_get()) {
			$data = session_get('form.' . $this->getAttribute('action') . '.data');
			if($data) {
				$this->loadData($data, true);
				$this->validate();
				session_clear('form.' . $this->getAttribute('action') . '.data');
			}
		} else {
			$this->loadData($_POST, true);
			if(!$this->validate()) {
				if(!is_ajax()) {
					// Save session data
					session_set('form.' . $this->getAttribute('action') . '.data', $_POST);
					// Redirect back
					redirect_back($base);
				} else {
					// Fall back to the return
				}
			} else {
				$handler = $this->getAttribute('handler');
				if(!$handler || !is_callable($handler)) {
					throw new beatbox\errors\Exception('No handler provided or non-callable handler provided.');
				}
				return call_user_func($handler, $this, $this->getData());
			}
		}
		return $this;
	}

	protected function getFields(:x:base $base = null) : Generator {
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

	protected static function add_to_map(Map $base, string $name, $value) {
		if(preg_match('#^(.+?)\[(.*?)\](.*)$#', $name, $matches)) {
			if(!$matches[2]) {
				if(!isset($base[$matches[1]])) {
					$base[$matches[1]] = Vector {};
				}
				$base[$matches[1]][] = $value;
			} else {
				if(!isset($base[$matches[1]])) {
					$base[$matches[1]] = Map {};
				}
				self::add_to_map($base[$matches[1]], $matches[2] . $matches[3], $value);
			}
		} else {
			$base[$name] = $value;
		}
	}

	protected function getData() {
		$data = Map {};
		foreach($this->getFields() as $field) {
			if(!$field->getAttribute('name')) {
				continue;
			}
			self::add_to_map($data, $field->getAttribute('name'), $field->getValue());
		}
		return $data;
	}

	protected static $load_count;

	protected static function get_value($data, $name, $base) {
		if(!$name) {
			return $data;
		} elseif(preg_match('#^(.+?)\[(.*?)\](.*)$#', $name, $matches)) {
			if(!$matches[2]) {
				$index = isset(self::$load_count[$base . $matches[1]]) ? self::$load_count[$base . $matches[1]] + 1 : 0;
				self::$load_count[$base . $matches[1]] = $index;

				if(isset($data[$matches[1]][$index])) {
					return $data[$matches[1]][$index];
				} else {
					return null;
				}
			} elseif(isset($data[$matches[1]])) {
				$base .= "$matches[1]-";
				return self::get_value($data[$matches[1]], $matches[2].$matches[3], $base);
			} else {
				return null;
			}
		} elseif(isset($data[$name])) {
			return $data[$name];
		} else {
			return null;
		}
	}

	public function loadData($data, bool $empty = false) {
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
			} elseif($empty) {
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
		$valid = $errors->Count() == 0;
		$validator = $this->getAttribute('validator');
		if(is_callable($validator)) {
			$valid = call_user_func($validator, $fields, $errors) && $valid;
		}
		return $valid;
	}
}
