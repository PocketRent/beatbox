<?php

namespace beatbox\test;

use beatbox;

class TimeFieldTest extends beatbox\Test {
	/**
	 * @group sanity
	 */
	public function testRequiredValidation() {
		$field = <pr:form:time />;

		$field->setAttribute('required', true);
		$this->assertFalse($field->validate()[0]);

		$field->setValue('12:15');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	/**
	 * @group sanity
	 */
	public function testMinHourValidation() {
		$field = <pr:form:time />;

		$field->setAttribute('minHour', 13);
		$field->setValue('12:59');
		$this->assertFalse($field->validate()[0]);

		$field->setValue('15:18');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	/**
	 * @group sanity
	 */
	public function testMaxHourValidation() {
		$field = <pr:form:time />;

		$field->setAttribute('maxHour', 11);
		$field->setValue('12:59');
		$this->assertFalse($field->validate()[0]);

		$field->setValue('05:48');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	/**
	 * @group sanity
	 * @depends testMinHourValidation
	 */
	public function testMinuteStepValidation() {
		$field = <pr:form:time />;

		$field->setAttribute('minHour', 12);
		$field->setAttribute('minuteStep', 5);

		$field->setValue('12:08');
		$this->assertFalse($field->validate()[0]);

		$field->setValue('15:15');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}
}
