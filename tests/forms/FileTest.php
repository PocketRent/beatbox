<?hh

namespace beatbox\test;

use beatbox;

class FileFieldTest extends beatbox\Test {
	private $html = [
		'name' => 'SomeFile.html',
		'type' => 'text/html',
		'size' => 2528,
		'tmp_name' => 'html.html',
	];

	private $jpeg = [
		'name' => 'SomeFile.jpg',
		'type' => 'image/jpeg',
		'size' => 1028,
		'tmp_name' => 'jpeg.jpg',
	];

	private $mp4 = [
		'name' => 'SomeFile.m4a',
		'type' => 'audio/mp4',
		'size' => 50171,
		'tmp_name' => 'mp4.m4a',
	];

	private $mpeg = [
		'name' => 'SomeFile.mpg',
		'type' => 'video/mp4',
		'size' => 730573,
		'tmp_name' => 'mpeg.mpg',
	];

	private $png = [
		'name' => 'SomeFile.PNG',
		'type' => 'image/png',
		'size' => 1103,
		'tmp_name' => 'png.PNG',
	];

	private function getFile($var) {
		$file = $this->$var;
		$file['tmp_name'] = __DIR__ . '/files/' . $file['tmp_name'];
		$file['error'] = UPLOAD_ERR_OK;
		return $file;
	}

	private function getFiles($vars) {
		if(count($vars) == 1) {
			return $this->getFile($vars[0]);
		}
		$files = [
			'name' => [],
			'type' => [],
			'size' => [],
			'tmp_name' => [],
			'error' => [],
		];
		foreach($vars as $var) {
			if(is_numeric($var)) {
				$file = $this->getError($var);
			} else {
				$file = $this->getFile($var);
			}
			foreach(array_keys($files) as $k) {
				$files[$k][] = $file[$k];
			}
		}
		return $files;
	}

	private function getError($code) {
		$file = [
			'name' => 'error+file.txt',
			'type' => 'text/plain',
			'size' => 0,
			'tmp_name' => '',
			'error' => $code
		];
		return $file;
	}

	/**
	 * @group sanity
	 */
	public function testBaseValidation() {
		$field = <bb:form:file />;

		$field->setValue($this->getError(UPLOAD_ERR_INI_SIZE));
		$this->assertFalse($field->validate()[0]);

		$field->setValue($this->getFile('html'));
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);

		$field->setValue($this->getError(UPLOAD_ERR_PARTIAL));
		$this->assertFalse($field->validate()[0]);

		try {
			$field->setValue($this->getError(UPLOAD_ERR_NO_TMP_DIR));
			$field->validate();
			$this->fail('Exception expected');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(500, $e->getBaseCode());
		}

		try {
			$field->setValue($this->getError(UPLOAD_ERR_CANT_WRITE));
			$field->validate();
			$this->fail('Exception expected');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(500, $e->getBaseCode());
		}
	}

	/**
	 * @group fast
	 * @depends testBaseValidation
	 */
	public function testGetValue() {
		$field = <bb:form:file />;
		$field->setValue($this->getFile('html'));

		$this->assertEquals($this->getFile('html'), $field->getValue());

		$field->setValue(null);
		$this->assertEmpty($field->getValue());

		$field->setValue($this->getError(UPLOAD_ERR_INI_SIZE));
		$this->assertEmpty($field->getValue());
	}

	// accept, multiple, required
	public function testRequiredValidation() {
		$field = <bb:form:file required="true" />;

		$field->setValue(null);

		$this->assertFalse($field->validate()[0]);

		$field->setValue($this->getFile('html'));
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	/**
	 * @group sanity
	 */
	public function testAcceptValidation() {
		$field = <bb:form:file />;

		// test image/*
		$field->setAttribute('accept', 'image/*');
		$field->setValue($this->getFile('jpeg'));
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);

		$field->setValue($this->getFile('mpeg'));
		$this->assertFalse($field->validate()[0]);

		// test video/*
		$field->setAttribute('accept', 'video/*');
		$field->setValue($this->getFile('mpeg'));
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);

		$field->setValue($this->getFile('png'));
		$this->assertFalse($field->validate()[0]);

		// test audio/*
		$field->setAttribute('accept', 'audio/*');
		$field->setValue($this->getFile('mp4'));
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);

		$field->setValue($this->getFile('mpeg'));
		$this->assertFalse($field->validate()[0]);

		// test mime type
		$field->setAttribute('accept', 'text/html');
		$field->setValue($this->getFile('html'));
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);

		$field->setValue($this->getFile('mpeg'));
		$this->assertFalse($field->validate()[0]);

		// test extension
		$field->setAttribute('accept', '.png');
		$field->setValue($this->getFile('png'));
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);

		$field->setValue($this->getFile('mpeg'));
		$this->assertFalse($field->validate()[0]);

		// test multiple (audio/* and .png)
		$field->setAttribute('accept', 'audio/*,.png');
		$field->setValue($this->getFile('png'));
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);

		$field->setValue($this->getFile('mp4'));
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);

		$field->setValue($this->getFile('mpeg'));
		$this->assertFalse($field->validate()[0]);
	}

	/**
	 * @group fast
	 * @depends testBaseValidation
	 */
	public function testMultipleBaseValidation() {
		$field = <bb:form:file multiple="true" />;

		$field->setValue($this->getFiles(['html', UPLOAD_ERR_INI_SIZE]));
		$this->assertFalse($field->validate()[0]);

		$field->setValue($this->getFiles(['html', 'png']));
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);

		$field->setValue($this->getFiles(['mpeg', UPLOAD_ERR_PARTIAL]));
		$this->assertFalse($field->validate()[0]);

		try {
			$field->setValue($this->getFiles(['jpeg', UPLOAD_ERR_NO_TMP_DIR]));
			$field->validate();
			$this->fail('Exception expected');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(500, $e->getBaseCode());
		}

		try {
			$field->setValue($this->getFiles(['jpeg', UPLOAD_ERR_CANT_WRITE]));
			$field->validate();
			$this->fail('Exception expected');
		} catch(beatbox\errors\HTTP_Exception $e) {
			$this->assertEquals(500, $e->getBaseCode());
		}
	}

	/**
	 * @depends testRequiredValidation
	 */
	public function testMultipleRequiredValidation() {
		$field = <bb:form:file multiple="true" required="true" />;

		$field->setValue(null);

		$this->assertFalse($field->validate()[0]);

		$field->setValue($this->getFiles(['html', 'png']));
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);
	}

	/**
	 * @depends testAcceptValidation
	 */
	public function testMultipleAcceptValidation() {
		$field = <bb:form:file multiple="true" />;

		// test image/*
		$field->setAttribute('accept', 'image/*');
		$field->setValue($this->getFiles(['jpeg', 'png']));
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);

		$field->setValue($this->getFiles(['jpeg', 'mpeg']));
		$this->assertFalse($field->validate()[0]);

		// test multiple (audio/* and .png)
		$field->setAttribute('accept', 'audio/*,.png');
		$field->setValue($this->getFiles(['png', 'mp4']));
		$errors = $field->validate();
		$this->assertTrue($errors[0], $errors[1]);

		$field->setValue($this->getFiles(['mp4', 'mpeg']));
		$this->assertFalse($field->validate()[0]);
	}
}
