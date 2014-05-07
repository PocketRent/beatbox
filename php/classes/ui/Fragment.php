<?hh

final class :bb:fragment extends :bb:base {
	attribute
		:div,
		string name @required;

	protected ImmSet<string> $skipTransfer = ImmSet {'name'};

	protected function compose() : :bb:fragment-data {
		$name = (string)$this->getAttribute('name');
		$fragment = beatbox\Router::response_for_fragment($name);

		return <bb:fragment-data name={$name}>{$fragment}</bb:fragment-data>;
	}
}

final class :bb:fragment-data extends :bb:base {
	attribute
		:bb:fragment;

	protected ImmSet<string> $skipTransfer = ImmSet {'name'};

	protected function compose(): :div {
		$name = (string)$this->getAttribute('name');

		return <div class="fragment" data-fragment-name={$name}>
			{$this->getChildren()}
		</div>;
	}
}
