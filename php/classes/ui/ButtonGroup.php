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

		$firstChild = $children[0];
		$lastChild = $children[count($children)-1];
		assert($firstChild instanceof :bb:button);
		assert($lastChild instanceof :bb:button);

		if (count($children) == 1) {
			return $firstChild;
		}

		if ($this->getAttribute('reversed')) {
			$firstChild->addClass('right');
			$lastChild->addClass('left');
		} else {
			$firstChild->addClass('left');
			$lastChild->addClass('right');
		}

		for($i=1; $i < (count($children)-1); $i++) {
			$child = $children[$i];
			assert($child instanceof :bb:button);
			$child->addClass('middle');
		}

		return <x:frag>{$children}</x:frag>;
	}
}
