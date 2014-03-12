<?hh

/**
 * This is a listbox, which is an iteractive list of items.
 * The name is chosen to match the pattern in http://www.w3.org/TR/wai-aria-practices/#Listbox
 * and the implementation reflects that as well.
 */
class :bb:listbox extends :bb:base {
	attribute
		:ul,
		string fallback = "icon",
		enum { 'small', 'medium', 'large' } size @required;

	children (:bb:list-item)*;

	protected Set<string> $skipTransfer = Set { 'fallback' };

	protected function compose() : :ul {
		$list = <ul class="listbox" role="listbox" />;
		$list->addClass($this->getAttribute('size'));

		$first = true;
		foreach ($this->getChildren() as $item) {
			$list->appendChild($item);
			if (device_type() == DEVICE_DESKTOP) {
				if ($first) {
					$item->setAttribute('tabindex', '0');
					$first = false;
				} else {
					$item->setAttribute('tabindex', '-1');
				}
			}
		}

		return $list;
	}
}

class :bb:list-item extends :bb:base {
	attribute :li;

	protected Set<string> $skipTransfer = Set {'icon'};

	protected function compose() : :li {
		return <li role="option">{$this->getChildren()}</li>;
	}
}
