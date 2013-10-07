<?php

namespace pr\test;

use pr\base, Map, Vector;

class FormTest extends base\Test {
	/**
	 * @group fast
	 */
	public function testCSRF() {
		// Make sure that a csrf input is added.
		$form = <pr:form />;
		$this->assertContains('name="__csrf"', (string)$form);
	}

	/**
	 * @group fast
	 */
	public function testFragmentHandling() {
		$form = <pr:form />;
		$form->forFragment(['a', 'b'], 'test');

		$this->assertEquals('post', $form->getAttribute('method'));
		$this->assertEquals('a/b?fragments=test', $form->getAttribute('action'));

		$form->setAttribute('method', 'get');
		$form->forFragment(['b', 'b'], 'testing');

		// Overriding shouldn't happen
		$this->assertEquals('get', $form->getAttribute('method'));
		$this->assertEquals('a/b?fragments=test', $form->getAttribute('action'));

		$form->removeAttribute('action');

		// Special case
		$form->forFragment(['/'], 'testing');
		$this->assertEquals('get', $form->getAttribute('method'));
		$this->assertEquals('/?fragments=testing', $form->getAttribute('action'));
	}

	/**
	 * @group fast
	 */
	public function testLoadData() {
		$f1 = <pr:form:text name="Field1" />;
		$f2 = <pr:form:text name="Field2" />;

		$form = <pr:form>
			{$f1}
			<div>
				{$f2}
			</div>
		</pr:form>;

		$form->loadData(['Field1' => 'Value1', 'Field2' => 'Value2']);

		$this->assertEquals('Value1', $f1->getValue());
		$this->assertEquals('Value2', $f2->getValue());

		$form->loadData(['Field1' => 'Value3'], true);

		$this->assertEquals('Value3', $f1->getValue());
		$this->assertEmpty($f2->getValue());
	}

	/**
	 * @group fast
	 */
	public function testLoadNestedData() {
		$f1 = <pr:form:text name="Field1[]" />;
		$f2 = <pr:form:text name="Field1[]" />;

		$f3 = <pr:form:text name="Field3[]" />;
		$f4 = <pr:form:text name="Field3[]" />;

		$form = <pr:form>
			{$f1}{$f3}
			{$f2}{$f4}
		</pr:form>;

		$form->loadData(['Field1' => ['a', 'b'], 'Field3' => ['c', 'd']]);
		$this->assertEquals('a', $f1->getValue());
		$this->assertEquals('b', $f2->getValue());
		$this->assertEquals('c', $f3->getValue());
		$this->assertEquals('d', $f4->getValue());

		$form->loadData(['Field1' => ['a'], 'Field3' => ['c']], true);
		$this->assertEquals('a', $f1->getValue());
		$this->assertEmpty($f2->getValue());
		$this->assertEquals('c', $f3->getValue());
		$this->assertEmpty($f4->getValue());

		$f1 = <pr:form:text name="Field[f1][]" />;
		$f2 = <pr:form:text name="Field[f1][]" />;

		$f3 = <pr:form:text name="Field[f3][]" />;
		$f4 = <pr:form:text name="Field[f3][]" />;

		$form = <pr:form>
			{$f1}{$f3}
			{$f2}{$f4}
		</pr:form>;

		$form->loadData(['Field' => ['f1' => ['a', 'b'], 'f3' => ['c', 'd']]]);
		$this->assertEquals('a', $f1->getValue());
		$this->assertEquals('b', $f2->getValue());
		$this->assertEquals('c', $f3->getValue());
		$this->assertEquals('d', $f4->getValue());

		$form->loadData(['Field' => ['f1' => ['a'], 'f3' => ['c']]], true);
		$this->assertEquals('a', $f1->getValue());
		$this->assertEmpty($f2->getValue());
		$this->assertEquals('c', $f3->getValue());
		$this->assertEmpty($f4->getValue());
	}

	/**
	 * @group fast
	 */
	public function testValidate() {
		$field = <pr:form:text name="Field" value="Value" required="true" />;
		$form = <pr:form>{$field}</pr:form>;

		$called = false;

		$form->setAttribute('validator', function($fields, $errors) use($field, &$called) {
			$called = true;
			$this->assertSame($fields['Field'], $field);
			return true;
		});

		$this->assertTrue($form->validate());
		$this->assertTrue($called);

		$field->setValue(null);

		$called = false;

		$this->assertFalse($form->validate());
		$this->assertTrue($called);

		$field->setValue('Value');

		$this->assertTrue($form->validate());

		$form->setAttribute('validator', function($fields, $errors) {
			$errors['Field'] = 'Error';
		});

		$this->assertFalse($form->validate());
	}

	/**
	 * @group fast
	 */
	public function testValidSubmission() {
		$field = <pr:form:text name="Field" value="Value" required="true" />;
		$form = <pr:form>{$field}</pr:form>;

		$called = false;

		$_POST['Field'] = 'test';
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$form->setAttribute('handler', function($f, $d) use($form, &$called) {
			$called = true;
			$this->assertSame($form, $f);
			$this->assertEquals('test', $d['Field']);
		});

		$form->forFragment(['/'], 'form');
		$this->assertTrue($called);
	}

	/**
	 * @group fast
	 */
	public function testInvalidSubmission() {
		$field = <pr:form:text name="Field" value="Value" required="true" />;
		$form = <pr:form>{$field}</pr:form>;

		$called = false;

		$_POST['Field'] = '';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHTTPRequest';

		$form->setAttribute('handler', function($f, $d) use($form, &$called) {
			$called = true;
			$this->assertSame($form, $f);
		});

		$res = $form->forFragment(['/'], 'form');
		$this->assertFalse($called);
		$this->assertSame($form, $res);

		try {
			$_SERVER['HTTP_X_REQUESTED_WITH'] = 'No AJAX';
			$form->forFragment(['/'], 'form');
			$this->fail('Non-AJAX form validation should throw an exception');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals(302, $e->getBaseCode());
		}
	}

	/**
	 * @group fast
	 * @depends testLoadData
	 */
	public function testReloadData() {
		$field = <pr:form:text name="Field" value="Value" required="true" />;
		$form = <pr:form>{$field}</pr:form>;

		$called = false;

		$_POST['Field'] = '';
		$_SERVER['REQUEST_METHOD'] = 'POST';

		try {
			$this->assertEquals('Value', $field->getValue());
			$form->forFragment(['/'], 'form');
			$this->fail('Should have errored');
		} catch(base\errors\HTTP_Exception $e) {
			$this->assertEquals('', $field->getValue());
		}
		$field->setValue('Value');
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$form->forFragment(['/'], 'form');
		$this->assertEquals('', $field->getValue());
	}

	/**
	 * @group fast
	 * @depends testValidSubmission
	 */
	public function testPullOutNestedData() {
		$form = <pr:form>
			<pr:form:text name="Field[]" value="Value" required="true" />;
			<pr:form:text name="Field[]" value="Value" required="true" />;
			<pr:form:text name="Field[]" value="Value" required="true" />;
		</pr:form>;

		$called = false;

		$_POST['Field'] = ['test', 'testing', 'd'];
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$form->setAttribute('handler', function($f, $d) use($form, &$called) {
			$called = true;
			$this->assertSame($form, $f);
			$this->assertEquals(Map { 'Field' => Vector {'test', 'testing', 'd'} }, $d);
		});

		$form->forFragment(['/'], 'form');
		$this->assertTrue($called);

		$form = <pr:form>
			<pr:form:text name="Field[a][]" value="Value" required="true" />;
			<pr:form:text name="Field[b][]" value="Value" required="true" />;
			<pr:form:text name="Field[a][]" value="Value" required="true" />;
		</pr:form>;

		$called = false;

		$_POST['Field'] = ['a' => ['a', 'c'], 'b' => ['b']];
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$form->setAttribute('handler', function($f, $d) use($form, &$called) {
			$called = true;
			$this->assertSame($form, $f);
			$this->assertEquals(Map { 'Field' => Map { 'a' => Vector {'a', 'c'}, 'b' => Vector {'b'}} }, $d);
		});

		$form->forFragment(['/'], 'form');
		$this->assertTrue($called);
	}
}
