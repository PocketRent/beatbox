<?hh

class :bb:t extends :bb:base {
	children (pcdata)*;

	attribute
		string label @required,
		Traversable args = [];

	protected function compose() {
		$message = (string)((<bb:raw />)->appendChild($this->getChildren()));
		if(($args = $this->getAttribute('args'))) {
			$callingArgs = [$message];
			foreach($args as $v) $callingArgs[] = $v;
			$message = call_user_func_array('sprintf', $callingArgs);
		}
		return <x:frag>{$message}</x:frag>;
	}
}
