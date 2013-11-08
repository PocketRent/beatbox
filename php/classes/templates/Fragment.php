<?hh

final class :bb:fragment extends :bb:base {
	attribute
		:div,
		string name @required;

	protected $skipTransfer = Set<string>{'name'};

	protected function compose() : :div {
		$name = $this->getAttribute('name');
		$fragment = beatbox\Router::response_for_fragment($name);

		return <div class="fragment" data-fragment-name={$name}>{$fragment}</div>;
	}
}
