<?hh

class :bb:t extends :bb:base {
	children (pcdata)*;

	attribute
		string label @required,
		Traversable args = [];

	protected function compose() : :x:frag {
		$message = (string)((<bb:raw />)->appendChild($this->getChildren()));
		if(($args = $this->getAttribute('args'))) {
			// $args is a Traversable, so we should turn it into an array
			// before handing it to vsprintf
			$newArgs = [];
			foreach ($args as $a) $newArgs[] = $a;
			$message = vsprintf($message, $args);
		}
		return <x:frag>{$message}</x:frag>;
	}
}
