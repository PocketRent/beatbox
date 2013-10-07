<?php

namespace pr\test;

use pr\base;

class DateFieldTest extends base\Test {
	/**
	 * @group sanity
	 */
	public function testRequiredValidation() {
		$field = <pr:form:date />;

		$field->setAttribute('required', true);
		$this->assertFalse($field->validate()[0]);

		$field->setValue('now');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	/**
	 * @group sanity
	 */
	public function testMinValidation() {
		$field = <pr:form:date />;

		$field->setAttribute('min', new \DateTime('2012-10-10'));
		$field->setValue('2012-01-01');
		$this->assertFalse($field->validate()[0]);

		$field->setValue('2013-10-10');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	/**
	 * @group sanity
	 */
	public function testMaxValidation() {
		$field = <pr:form:date />;

		$field->setAttribute('max', new \DateTime('2012-10-10'));
		$field->setValue('2013-01-01');
		$this->assertFalse($field->validate()[0]);

		$field->setValue('2012-01-01');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	/**
	 * @group sanity
	 * @depends testMinValidation
	 */
	public function testStepValidation() {
		$field = <pr:form:date />;

		$field->setAttribute('min', new \DateTime('2012-10-10'));
		$field->setAttribute('step', 5);

		$field->setValue('2012-10-14');
		$this->assertFalse($field->validate()[0]);

		$field->setValue('2012-10-15');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}
}
