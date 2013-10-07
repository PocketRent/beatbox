<?php

class :pr:form:textarea extends :pr:form:field {
	attribute :textarea;

	protected $type = 'textarea';

	children (pcdata)*;

	public function setValue($value) {
		$this->replaceChildren([<x:frag>{$value}</x:frag>]);
		$this->reset();
	}

	protected function buildField() {
		$root = <textarea class="textarea" />;
		$root->appendChild($this->getChildren());
		return $root;
	}

	public function getValue() {
		return implode($this->getChildren());
	}
}
