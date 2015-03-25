<?hh

class :bb:form:password extends :bb:form:field {
	attribute :input;

	protected ?string $type = 'password';

	protected function buildField() : :x:composable-element {
		if($this->isAttributeSet('value')) {
			$this->removeAttribute('value');
		}
		return parent::buildField();
	}
}
