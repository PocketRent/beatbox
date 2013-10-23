<?hh

namespace beatbox\test;

use beatbox;

class CheckboxFieldTest extends beatbox\Test {
	/**
	 * @group fast
	 */
	public function testRequiredValidation() {
		$field = <bb:form:checkbox required="true" />;
		$field->setValue(true);

		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);

		$field->setValue(null);
		$this->assertFalse($field->validate()[0]);

		$field->setAttribute('value', 'value');

		$field->setValue(true);
		$this->assertFalse($field->validate()[0]);

		$field->setValue('value');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}
}
