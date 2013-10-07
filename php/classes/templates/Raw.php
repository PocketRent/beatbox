<?php

final class :pr:raw extends :xhp:raw-pcdata-element {
	category %flow, %phrase;

	children (pcdata*);

	protected function stringify() {
		$buf = '';
		foreach ($this->getChildren() as $child) {
			if (!is_string($child)) {
				throw new XHPClassException($this, 'Child must be a string');
			}
			$buf .= $child;
		}
		return $buf;
	}
}
