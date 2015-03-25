<?hh // strict

class :bb:form:email extends :bb:form:field {
	attribute :input;

	protected ?string $type = 'email';

	protected function buildField() : :x:composable-element {
		if ($this->getAttribute('multiple')) {
			return <bb:form:textarea>{$this->getValue()}</bb:form:textarea>;
		} else {
			return parent::buildField();
		}
	}

	public function validate() : (bool, string) {
		$value = (string)$this->getValue();
		if($value) {
			if($this->getAttribute('multiple')) {
				$values = array_filter(array_map(fun('trim'), explode(',', $value)));
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
