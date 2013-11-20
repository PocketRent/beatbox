<?hh

class :bb:form:radio extends :bb:form:field {
	attribute :input;

	protected string $type = 'radio';

	public function setValue(mixed $value) : :bb:form:radio {
		$v = $this->getAttribute('value');
		if(($v && (string)$value == $v) || (!$v && $value)) {
			$this->setAttribute('checked', true);
		} else {
			$this->setAttribute('checked', false);
		}
		$this->reset();
		return $this;
	}

	public function getValue() : mixed {
		if($this->getAttribute('checked')) {
			return $this->getAttribute('value') ?: true;
		}
		return false;
	}
}
