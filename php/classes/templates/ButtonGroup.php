<?hh

/**
 * Button group for applying the correct classes for a group
 * of buttons
 */
class :bb:button-group extends :bb:base {
	attribute
		bool reversed = false;

	children (:bb:button)+;

	protected function compose() : :x:frag {
		$children = $this->getChildren();

		if (count($children) == 1) {
			return $children[0];
		}

		if ($this->getAttribute('reversed')) {
			$children[0]->addClass('right');
			$children[count($children)-1]->addClass('left');
		} else {
			$children[0]->addClass('left');
			$children[count($children)-1]->addClass('right');
		}

		for($i=1; $i < (count($children)-1); $i++) {
			$children[$i]->addClass('middle');
		}

		return <x:frag>{$children}</x:frag>;
	}
}
