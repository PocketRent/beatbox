<?hh

namespace beatbox\test;

use beatbox;

class TextareaFieldTest extends beatbox\Test {
	public function testRequiredValidation() {
		$field = <bb:form:textarea required="true" />;
		$field->setValue('');

		$this->assertFalse($field->validate()[0]);

		$field->setValue('value');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	public function testMaxlengthValidation() {
		$field = <bb:form:textarea maxlength="3" />;
		$field->setValue('1234');
		$this->assertFalse($field->validate()[0]);

		$field->setValue('');

		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}
}
