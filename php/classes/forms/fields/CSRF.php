<?hh

final class :bb:form:csrf extends :bb:form:field {
	protected function buildField() : :bb:form:hidden {
		return <bb:form:hidden name="__csrf" value={get_csrf_token()} />;
	}

	public function getValue() : string {
		return get_csrf_token();
	}

	public function setValue(mixed $value) {
		// nop
	}
}
