<?hh

class :bb:button extends :bb:base {
	attribute
		:button,
		enum {'modal', 'action', 'link'} kind = 'action',
		bool confirm = false,
		enum {'x-small', 'small', 'medium', 'large', 'x-large'} size = 'medium',
		enum {'red', 'green', 'blue', 'dark', 'grey', 'white', 'orange'} colour = 'blue',
		enum {'left', 'middle', 'right'} position,
		string fragment, // Used for modal-kind buttons
		string href;

	protected function compose() : :button {
		$kind = (string)$this->getAttribute('kind');

		$button = <button />;
		$button->addClass($kind)
				->addClass((string)$this->getAttribute('size'))
				->addClass((string)$this->getAttribute('colour'))
				->addClass((string)$this->getAttribute('position'))
				->addClass((string)$this->getAttribute('confirm') ? 'confirm' : '')
				->appendChild($this->getChildren());

		if ($this->getAttribute('href'))
			$button->setAttribute('data-href', (string)$this->getAttribute('href'));

		switch ($kind) {
		case 'link':
			if (!$this->getAttribute('role'))
				$button->setAttribute('role', 'link');
			break;
		case 'modal':
			if ($this->getAttribute('fragment'))
				$button->setAttribute('data-fragment', (string)$this->getAttribute('fragment'));
			break;
		}

		return $button;
	}
}
