<?php

class :pr:form:checkbox extends :pr:form:field {
	attribute :input;

	protected $type = 'checkbox';

	public function setValue($value) {
		$v = $this->getAttribute('value');
		if(($v && (string)$value == $v) || (!$v && $value)) {
			$this->setAttribute('checked', true);
		} else {
			$this->setAttribute('checked', false);
		}
		$this->reset();
	}

	public function getValue() {
		if($this->getAttribute('checked')) {
			return $this->getAttribute('value') ?: true;
		}
		return false;
	}
}
