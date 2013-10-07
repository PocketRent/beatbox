<?php

namespace beatbox\test;

use beatbox;

class NumberFieldTest extends beatbox\Test {
	/**
	 * @group fast
	 */
	public function testRequiredValidation() {
		$field = <pr:form:number />;

		$field->setAttribute('required', true);
		// 0 is deliberately not valid when the field is required
		$field->setValue(0);
		$this->assertFalse($field->validate()[0]);

		$field->setValue(12);
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	public function testMaxValidation() {
		$field = <pr:form:number />;

		$field->setAttribute('max', 10);
		$field->setValue(20);
		$this->assertFalse($field->validate()[0]);

		$field->setValue(2);
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	public function testMinValidation() {
		$field = <pr:form:number />;

		$field->setAttribute('min', 10);
		$field->setValue(0);
		$this->assertFalse($field->validate()[0]);

		$field->setValue(12);
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	/**
	 * @depends testMinValidation
	 */
	public function testStepValidation() {
		$field = <pr:form:number />;

		$field->setAttribute('step', 5);
		$field->setAttribute('min', 10);
		$field->setValue(11);
		$this->assertFalse($field->validate()[0]);

		$field->setValue(10);
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);

		$field->setAttribute('step', 0.5);
		$field->setValue(10.3);
		$this->assertFalse($field->validate()[0]);

		$field->setValue(11);
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	/**
	 * @group sanity
	 */
	public function testEmptyValidation() {
		$field = <pr:form:number max="2" min="3" step="5" />;
		$field->setValue(null);

		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);

		$field->setValue('');

		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}
}
