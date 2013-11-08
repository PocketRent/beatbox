<?hh

class :bb:form:label extends :bb:form:field {
	attribute
		:label,
		:bb:form:field for @required;

	protected function buildField() : :label {
		$root = <label for={$this->getAttribute('for')->getID()} />;
		$root->appendChild($this->getChildren());
		return $root;
	}

	public function setValue(mixed $value) : :bb:form:label {
		// nop
		return $this;
	}

	public function getValue() : null {
		return null;
	}

	// a label is always valid
	public function validate() : array {
		return [true, null];
	}
}
