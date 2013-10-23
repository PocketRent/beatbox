<?hh

namespace beatbox\test;

use beatbox;

class CSRFFieldTest extends beatbox\Test {
	/**
	 * @group sanity
	 */
	public function testSetValue() {
		$field = <bb:form:csrf />;

		$existing = $field->getValue();

		$field->setValue('null');
		$this->assertNotEquals('null', $field->getValue());
		$this->assertEquals($existing, $field->getValue());
	}
}
