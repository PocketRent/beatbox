<?hh // strict

const int DEVICE_DESKTOP = 1;
const int DEVICE_TABLET  = 2;
const int DEVICE_MOBILE  = 4;

const int DEVICE_ALL     = 7;

abstract class :bb:base extends :x:element {
	attribute
		int devices = DEVICE_ALL,
		string id;

	abstract protected function compose() : :x:composable-element;

	protected ImmSet<string> $skipTransfer = ImmSet {};

	final public function addClass(string $class) : :bb:base {
		$this->setAttribute(
			'class',
			trim((string)$this->getAttribute('class').' '.$class)
		);
		return $this;
	}

	public function getID() : string {
		return $this->requireUniqueID();
	}

	public function requireUniqueID() : string {
		if(!($id = (string)$this->getAttribute('id'))) {
			$this->setAttribute('id', $id = 'bb_' . (<div />)->getID());
		}
		return $id;
	}

	final protected function render() : :x:composable-element {
		$allowed = (int)$this->getAttribute('devices');
		if(!($allowed & device_type())) {
			return <x:frag />;
		}

		$root = $this->compose();
		if ($root === null) {
			return <x:frag />;
		} else if(is_string($root) || $root instanceof :x:frag) {
			return $root;
		}
		if (:x:base::$ENABLE_VALIDATION) {
			if (!$root instanceof :xhp:html-element
					&& !$root instanceof :bb:base) {
				throw new XHPClassException(
					$this,
					'compose() must return an xhp:html-element'.
					' or bb:base instance.'
				);
			}
		}

		$this->cascadeAttributes($root);

		return $root;
	}

	final protected function cascadeAttributes(:x:base $root) : void {
		// Get all attributes declared on this instance
		$attributes = $this->getAttributes();
		// Get all allowed attributes on the node returned
		// from compose()
		{ // UNSAFE
			$declared = $root->__xhpAttributeDeclaration();
		}

		$skip = $this->skipTransfer->toSet();
		$skip->addAll(array_keys($root->getAttributes()));

		// Transfer any classes that were added inline over
		// to the root node.
		if (array_key_exists('class', $attributes) && method_exists($root, 'addClass')) {
			$attributes['class'] && $root->addClass($attributes['class']);
			unset($attributes['class']);
		}

		// Always forward data and aria attributes
		$html5Attributes = array('data-' => true, 'aria-' => true);

		// Transfer all valid attributes to $root
		foreach ($attributes as $attribute => $value) {
			if (isset($declared[$attribute]) ||
					isset($html5Attributes[substr($attribute, 0, 5)])) {
				if($skip->contains($attribute)) {
					continue;
				}
				try {
					$root->setAttribute($attribute, $value);
				} catch (XHPInvalidAttributeException $e) {
					// This happens when the attribute defined on
					// your instance has a different definition
					// than the one you've returned. This usually
					// happens when you've defined different enum values.
					// When you turn off validation (like on prod) these
					// errors will not be thrown, so you should
					// fix your APIs to use different attributes.
					error_log(
						'Attribute name collision for '.$attribute.
						' in :bb:base::render() when transferring'.
						' attributes to a returned node. source: '.
						$this->source."\nException: ".$e->getMessage()
					);
				}
			}
		}
	}
}
