<?php

class :bb:form:label extends :bb:form:field {
	attribute
		:label,
		:bb:form:field for @required;

	protected function buildField() {
		$root = <label for={$this->getAttribute('for')->getID()} />;
		$root->appendChild($this->getChildren());
		return $root;
	}

	public function setValue($value) {
		// nop
	}

	public function getValue() {
		return null;
	}

	// a label is always valid
	public function validate() {
		return [true, null];
	}
}
