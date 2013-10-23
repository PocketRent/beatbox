<?hh

namespace beatbox\test;

use beatbox;

class EmailFieldTest extends beatbox\Test {
	/**
	 * @group sanity
	 */
	public function testBaseValidation() {
		$field = <bb:form:email />;

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
		$field = <bb:form:email />;

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
		$field = <bb:form:email />;

		$field->setAttribute('pattern', '\w@\w{2}\.\w{3}');
		$field->setValue('hello@pass.com');
		$this->assertFalse($field->validate()[0]);

		$field->setValue('a@bc.def');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	public function testRequiredValidation() {
		$field = <bb:form:email />;

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
		$field = <bb:form:email />;

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
		$field = <bb:form:email />;

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
