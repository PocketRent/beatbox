<?hh

final class :bb:form:csrf extends :bb:form:field {
	protected function buildField() {
		return <bb:form:hidden name="__csrf" value={get_csrf_token()} />;
	}

	public function getValue() {
		return get_csrf_token();
	}

	public function setValue($value) {
		// nop
	}
}
