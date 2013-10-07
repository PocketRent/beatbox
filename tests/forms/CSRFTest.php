<?php

namespace pr\test;

use pr\base;

class CSRFFieldTest extends base\Test {
	/**
	 * @group sanity
	 */
	public function testSetValue() {
		$field = <pr:form:csrf />;

		$existing = $field->getValue();
		
		$field->setValue('null');
		$this->assertNotEquals('null', $field->getValue());
		$this->assertEquals($existing, $field->getValue());
	}
}
