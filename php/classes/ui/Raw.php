<?hh

final class :bb:raw extends :xhp:raw-pcdata-element {
	category %flow, %phrase;

	children (pcdata*);

	protected function stringify() : string {
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
