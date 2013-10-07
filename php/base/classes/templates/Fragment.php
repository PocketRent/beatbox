<?php

final class :pr:fragment extends :pr:base {
	attribute
		:div,
		string name @required;

	protected $skipTransfer = Set<string>{'name'};

	protected function compose() {
		$name = $this->getAttribute('name');
		$fragment = pr\base\Router::response_for_fragment($name);

		return <div class="fragment" data-fragment-name={$name}>{$fragment}</div>;
	}
}
