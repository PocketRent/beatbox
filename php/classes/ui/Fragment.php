<?hh

final class :bb:fragment extends :bb:base {
	attribute
		:div,
		string name @required;

	protected ImmSet<string> $skipTransfer = ImmSet {'name'};

	protected function compose() : :div {
		$name = (string)$this->getAttribute('name');
		$fragment = beatbox\Router::response_for_fragment($name);

		return <div class="fragment" data-fragment-name={$name}>{$fragment}</div>;
	}
}
