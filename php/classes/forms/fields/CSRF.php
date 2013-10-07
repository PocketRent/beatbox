<?php

final class :pr:form:csrf extends :pr:form:field {
	protected function buildField() {
		return <pr:form:hidden name="__csrf" value={get_csrf_token()} />;
	}

	public function getValue() {
		return get_csrf_token();
	}

	public function setValue($value) {
		// nop
	}
}
