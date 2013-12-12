<?hh

abstract class :bb:form:field extends :bb:base {
	attribute
		string label,
		integer minlength;

	protected string $type;

	private static Set<string> $rangeVal = Set {'date', 'number'};

	private static Set<string> $lenVal = Set {'text', 'email', 'password', 'textarea'};

	private static Set<string> $patternVal = Set {'text', 'email', 'password'};

	protected $valid = true;
	protected $error;

	protected function buildField() : :input {
		if(!$this->type) {
			throw new InvalidArgumentException('A type must be set');
		}

		return <input type={$this->type} class={$this->type} />;
	}

	public function setValue($value) : :bb:form:field {
		$this->setAttribute('value', $value);
		$this->reset();
		return $this;
	}

	public function getValue() : mixed {
		return $this->getAttribute('value');
	}

	final protected function compose() : :x:frag {
		$label = $this->getAttribute('label');

		$root = <x:frag />;

		if($label) {
			// Force ID generation here, so that buildField() later has it,
			// as it is called before the label is rendered
			$this->getID();
			$root->appendChild(<bb:form:label for={$this}>{$label}</bb:form:label>);
			$root->appendChild(' ');
		}

		$field = $this;
		do {
			$prev = $field;
			$field = $field->buildField();
			$prev->cascadeAttributes($field);
		} while($field instanceof :bb:form:field);

		if (!$this->valid) {
			$root->setAttribute('aria-invalid', 'true');
			$field->setAttribute('class', $field->getAttribute('class') . ' error');
		}

		if ($this->isAttributeSet('required') && $this->getAttribute('required') == 'true') {
			$root->setAttribute('aria-required', 'true');
		}

		$root->appendChild($field);

		if(!$this->valid) {
			$err = $this->error ?: "Incorrect value";
			$root->appendChild(<span class="error">{$this->error}</span>);
		}

		return $root;
	}

	protected function reset() : void {
		$this->valid = true;
		$this->error = null;
	}

	public function setValid(bool $valid, string $error=null) : void {
		$this->valid = $valid;
		$this->error = $error;
	}

	public function getValid() : bool {
		return $this->valid;
	}

	public function validate() : array {
		$value = $this->getValue();
		$displayName = $this->getAttribute('label') ?: $this->getAttribute('name');
		// required
		if($this->valid && $this->getAttribute('required')) {
			if(!$value) {
				$this->valid = false;
				$this->error = $displayName . ' is required';
			}
		}
		// maxlength
		if($this->valid && self::$lenVal->contains($this->type)) {
			if(($len = $this->getAttribute('maxlength')) && mb_strlen($value) > $len) {
				$this->valid = false;
				$this->error = 'Maximum length is ' . $len;
			}
			if(($len = $this->getAttribute('minlength')) && mb_strlen($value) < $len) {
				$this->valid = false;
				$this->error = 'Minimum length is ' . $len;
			}
		}
		// pattern
		if($this->valid && self::$patternVal->contains($this->type) && $value !== null && $value !== '') {
			if(($pattern = $this->getAttribute('pattern'))) {
				$regex = '/^(?:' . str_replace('/', '\\/', $pattern) . ')$/';
				if($this->getAttribute('multiple')) {
					$values = array_filter(array_map('trim', explode(',', $value)));
					foreach($values as $value) {
						if(!preg_match($regex, $value)) {
							$this->valid = false;
							$this->error = $value . ' does not match allowed format for ' . $this->getAttribute('name');
						}
					}
				} else if(!preg_match($regex, $value)) {
					$this->valid = false;
					$this->error = $displayName . ' does not match allowed format';
				}
			}
		}
		// min, max, step
		if($this->valid && self::$rangeVal->contains($this->type) && $value !== null && $value !== '') {
			if(($min = $this->getAttribute('min'))) {
				if(compare_items($value, $min)) {
					$this->valid = false;
					$this->error = $displayName . ' is too low';
				} else if(($step = $this->getAttribute('step')) && strcasecmp($step, 'any') != 0) {
					$difference = item_difference($value, $min);
					$mult = (int)($difference / $step);
					if($difference - $step * $mult > 0) {
						$this->valid = false;
						$this->error = $displayName . ' is not a multiple of ' . $step . ' above the min value';
					}
				}
			}
			if($this->valid && ($max = $this->getAttribute('max'))) {
				if(compare_items($max, $value)) {
					$this->valid = false;
					$this->error = $displayName . ' is too high';
				}
			}
		}
		return [$this->valid, $this->error];
	}
}
