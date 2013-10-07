<?php

class :pr:t extends :pr:base {
	children (pcdata)*;

	attribute
		string label @required,
		Traversable args = [];

	protected function compose() {
		$message = (string)((<pr:raw />)->appendChild($this->getChildren()));
		if(($args = $this->getAttribute('args'))) {
			$callingArgs = [$message];
			foreach($args as $v) $callingArgs[] = $v;
			$message = call_user_func_array('sprintf', $callingArgs);
		}
		return <x:frag>{$message}</x:frag>;
	}
}
