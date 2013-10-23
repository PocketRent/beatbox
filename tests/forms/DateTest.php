<?hh

namespace beatbox\test;

use beatbox;

class DateFieldTest extends beatbox\Test {
	/**
	 * @group sanity
	 */
	public function testRequiredValidation() {
		$field = <bb:form:date />;

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
		$field = <bb:form:date />;

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
		$field = <bb:form:date />;

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
		$field = <bb:form:date />;

		$field->setAttribute('min', new \DateTime('2012-10-10'));
		$field->setAttribute('step', 5);

		$field->setValue('2012-10-14');
		$this->assertFalse($field->validate()[0]);

		$field->setValue('2012-10-15');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	/**
	 * @group fast
	 */
	public function testReturnType() {
		$field = <bb:form:date />;
		$field->setValue('2012-10-15');

		$value = $field->getValue();

		$this->assertTrue($value instanceof \beatbox\orm\DateTimeType);
	}
}
