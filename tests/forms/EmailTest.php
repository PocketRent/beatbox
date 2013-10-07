<?php

namespace pr\test;

use pr\base;

class EmailFieldTest extends base\Test {
	/**
	 * @group sanity
	 */
	public function testBaseValidation() {
		$field = <pr:form:email />;
		
		$field->setValue('@fail.com');
		$this->assertFalse($field->validate()[0]);

		$field->setValue('pass@pass.com');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	/**
	 * @group fast
	 */
	public function testMaxLengthValidation() {
		$field = <pr:form:email />;
		
		$field->setAttribute('maxlength', 11);
		$field->setValue('123456@aa.bb');
		$this->assertFalse($field->validate()[0]);

		$field->setValue('12345@aa.bb');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	/**
	 * @group sanity
	 */
	public function testPatternValidation() {
		$field = <pr:form:email />;

		$field->setAttribute('pattern', '\w@\w{2}\.\w{3}');
		$field->setValue('hello@pass.com');
		$this->assertFalse($field->validate()[0]);

		$field->setValue('a@bc.def');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	public function testRequiredValidation() {
		$field = <pr:form:email />;

		$field->setAttribute('required', true);
		$field->setValue(null);
		$this->assertFalse($field->validate()[0]);

		$field->setValue('hello@pass.com');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);

		$field->setAttribute('required', false);
	}

	/**
	 * @group sanity
	 */
	public function testMultipleBaseValidation() {
		$field = <pr:form:email />;

		$field->setAttribute('multiple', true);
		$field->setValue('@fail.com');
		$this->assertFalse($field->validate()[0]);

		$field->setValue('pass@pass.com');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);

		$field->setValue('pass@pass.com, @fail.com');
		$errors = $field->validate();
		$this->assertFalse($field->validate()[0]);

		$field->setValue('pass@pass.com, second-pass@pass.com, ');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	/**
	 * @group fast
	 */
	public function testMultiplePatternValidation() {
		$field = <pr:form:email />;

		$field->setAttribute('multiple', true);
		$field->setAttribute('pattern', '\w@\w{2}\.\w{3}');
		$field->setValue('hello@pass.com');
		$this->assertFalse($field->validate()[0]);

		$field->setValue('a@bc.def');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);

		$field->setValue('a@bc.def, hello@pass.com');
		$this->assertFalse($field->validate()[0]);

		$field->setValue('a@bc.def, , g@hi.jkl');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}
}
