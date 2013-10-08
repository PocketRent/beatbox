<?php

class :bb:form:email extends :bb:form:field {
	attribute :input;

	protected $type = 'email';

	public function validate() {
		$value = $this->getValue();
		if($value) {
			if($this->getAttribute('multiple')) {
				$values = array_filter(array_map('trim', explode(',', $value)));
				foreach($values as $value) {
					if(!filter_var($value, FILTER_VALIDATE_EMAIL)) {
						$this->valid = false;
						$this->error = 'Invalid email address "' . $value . '"';
						break;
					}
				}
			} else {
				if(!filter_var($value, FILTER_VALIDATE_EMAIL)) {
					$this->valid = false;
					$this->error = 'Invalid email address';
				}
			}
		}
		return parent::validate();
	}
}
