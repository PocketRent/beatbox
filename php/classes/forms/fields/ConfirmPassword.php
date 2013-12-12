<?hh

class :bb:form:confirm-password extends :bb:form:field {
	attribute
		:div,
		:bb:form:password,
		string name,
		string firstLabel,
		string secondLabel;

	protected string $type = 'password';

	protected Set<string> $skipTransfer = Set {'name', 'label'};

	protected function buildField() : :div {
		$first = <bb:form:password name={$this->getAttribute('name') . '[0]'} label={$this->getAttribute('firstLabel')} id={$this->getID()} class="first" />;
		$second = <bb:form:password name={$this->getAttribute('name') . '[1]'} label={$this->getAttribute('secondLabel')} class="second" />;

		$this->cascadeAttributes($first);
		$this->cascadeAttributes($second);

		return <div class="confirmPassword">
			{$first}
			{$second}
		</div>;
	}

	public function setValue(\mixed $value) : :bb:form:confirm-password {
		if(!is_array($value)) {
			parent::setValue($value);
		} else {
			$a = $value[0];
			$b = $value[1];
			if($a !== $b) {
				// Set first, cause of reset
				parent::setValue('');
				$this->valid = false;
				$this->error = 'Those two passwords did not match, please try again.';
			} else {
				parent::setValue($a);
			}
		}
		return $this;
	}
}
