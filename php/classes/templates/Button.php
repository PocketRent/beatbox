<?php

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

	protected function compose() {
		$kind = $this->getAttribute('kind');

		$button = <button />;
		$button->addClass($kind)
				->addClass($this->getAttribute('size'))
				->addClass($this->getAttribute('colour'))
				->addClass($this->getAttribute('position'))
				->addClass($this->getAttribute('confirm') ? 'confirm' : '')
				->appendChild($this->getChildren());

		if ($this->getAttribute('href'))
			$button->setAttribute('data-href', $this->getAttribute('href'));

		switch ($kind) {
		case 'link':
			if (!$this->getAttribute('role'))
				$button->setAttribute('role', 'link');
			break;
		case 'modal':
			if ($this->getAttribute('fragment'))
				$button->setAttribute('data-fragment', $this->getAttribute('fragment'));
			break;
		}

		return $button;
	}
}
