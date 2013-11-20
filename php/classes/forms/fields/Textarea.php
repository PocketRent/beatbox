<?hh

class :bb:form:textarea extends :bb:form:field {
	attribute :textarea;

	protected string $type = 'textarea';

	children (pcdata)*;

	public function setValue(mixed $value) : :bb:form:textarea {
		$this->replaceChildren([<x:frag>{$value}</x:frag>]);
		$this->reset();
		return $this;
	}

	protected function buildField() : :textarea {
		$root = <textarea class="textarea" />;
		$root->appendChild($this->getChildren());
		return $root;
	}

	public function getValue() : string {
		return implode($this->getChildren());
	}
}
