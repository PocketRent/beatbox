<?php

class :pr:form:password extends :pr:form:field {
	attribute :input;

	protected $type = 'password';

	protected function buildField() {
		if($this->isAttributeSet('value')) {
			$this->removeAttribute('value');
		}
		return parent::buildField();
	}
}
