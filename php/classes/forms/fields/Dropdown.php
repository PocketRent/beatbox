<?hh

class :bb:form:dropdown extends :bb:form:field {
	protected $type = 'dropdown';

	attribute
		:select,
		string value,
		string default,
		Traversable items;

	protected function buildField() : :select {
		$base = <select class="dropdown" />;
		if($this->getAttribute('default')) {
			$base->appendChild(<option value="">{$this->getAttribute('default')}</option>);
		}
		$items = $this->getAttribute('items') ?: $this->getChildren();
		if($items instanceof Continuation) {
			$items = clone $items;
		}
		foreach($items as $key=>$item) {
			if(is_object($item)) {
				$base->appendChild($item);
			} else {
				$base->appendChild(<option value={$key}>{$item}</option>);
			}
		}
		$this->selectValue((string)$this->getAttribute('value'), $base);
		return $base;
	}

	protected function childValueFields(:x:base $base = null) : Continuation {
		if(!$base) {
			$base = $this;
		}
		foreach($base->getChildren() as $child) {
			if(!is_object($child)) {
				continue;
			}
			if(isset($child->__xhpAttributeDeclaration()['value'])) {
				yield $child;
			} else {
				foreach($this->childValueFields($child) as $f) yield $f;
			}
		}
	}

	protected function selectValue(string $value, :x:base $base) : void {
		foreach($this->childValueFields($base) as $item) {
			if($value !== '' && $item->getAttribute('value') == $value) {
				$item->setAttribute('selected', true);
			} else {
				$item->removeAttribute('selected');
			}
		}
	}

	public function getValue() : mixed {
		$check = $this->getAttribute('value');
		$items = $this->getAttribute('items') ?: $this->getChildren();
		if($items instanceof Continuation) {
			$items = clone $items;
		}
		foreach($items as $key=>$item) {
			if(is_object($item)) {
				foreach($this->childValueFields(<x:frag>{$item}</x:frag>) as $i) {
					if($i->getAttribute('value') == $check) {
						return $check;
					}
				}
			} elseif($key == $check) {
				return $check;
			}
		}
		return null;
	}
}
