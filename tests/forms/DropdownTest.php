<?php

namespace pr\test;

use pr\base;

class DropdownFieldTest extends base\Test {
	/**
	 * @group fast
	 */
	public function testGetValue() {
		$field = <pr:form:dropdown><option value="Hello">Hello</option></pr:form:dropdown>;

		$this->assertEmpty($field->getValue());

		$field->setValue('Hello');
		$this->assertEquals('Hello', $field->getValue());

		$field->setValue('Bye');
		$this->assertEmpty($field->getValue());

		$field->appendChild(<optgroup label="H"><option value="Bye">B</option><option value="C">C</option></optgroup>);

		$this->assertEquals('Bye', $field->getValue());

		$field->setValue('C');
		$this->assertEquals('C', $field->getValue());

		$field = <pr:form:dropdown items={['a' => 'b']} />;
		$field->setValue('a');
		$this->assertEquals('a', $field->getValue());
	}

	/**
	 * @group fast
	 * @depends testGetValue
	 */
	public function testRequiredValidation() {
		$field = <pr:form:dropdown required="true"><option value="Hello">Hello</option></pr:form:dropdown>;

		$field->setValue('Bye');
		$this->assertFalse($field->validate()[0]);

		$field->setValue('Hello');
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	/**
	 * @group fast
	 */
	public function testSelectOption() {
		$field = <pr:form:dropdown>
			<option value="1">1</option>
			<optgroup label="2">
				<option value="3">3</option>
			</optgroup>
		</pr:form:dropdown>;

		$html = (string)$field;
		$this->assertNotContains('selected', $html);

		$field->setValue('1');

		$html = (string)$field;
		$this->assertContains('value="1" selected', $html);
		$this->assertContains('value="3">', $html);

		$field->setValue('3');

		$html = (string)$field;
		$this->assertContains('value="1">', $html);
		$this->assertContains('value="3" selected', $html);
	}
}
