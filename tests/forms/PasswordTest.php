<?hh

namespace beatbox\test;

use beatbox;

class PasswordFieldTest extends beatbox\Test {
	/**
	 * @group fast
	 */
	public function testRender() {
		$field = <bb:form:password />;
		$field->setValue('hello');
		$this->assertNotContains('hello', (string)$field);
	}

	public function testMaxlengthValidation() {
		$field = <bb:form:password maxlength="5" />;

		$field->setValue('123456');
		$this->assertFalse($field->validate()[0]);

		$field->setValue('12345');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	public function testPatternValidation() {
		$field = <bb:form:password pattern="[A-Za-z]{5,7}\d+" />;

		$field->setValue('ABCDEFGH12');
		$this->assertFalse($field->validate()[0]);

		$field->setValue('AbCdF12345');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	public function testRequiredValidation() {
		$field = <bb:form:password required="true" />;

		$field->setValue(null);
		$this->assertFalse($field->validate()[0]);

		$field->setValue('12');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}
}
