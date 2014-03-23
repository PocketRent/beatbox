<?hh

class :bb:form:file extends :bb:form:field {
	attribute
		:input,
		array value;

	protected string $type = 'file';

	protected ImmSet<string> $skipTransfer = ImmSet { 'value' };

	public function validate() : array {
		$value = $this->getAttribute('value');
		if($this->valid && is_array($value) && $value) {
			if(is_array($value['error'])) {
				$keys = array_keys($value);
				foreach(array_keys($value['error']) as $k) {
					$values = array_column($value, $k);
					$file = array_combine($keys, $values);
					$this->validateFile($file);
					if(!$this->valid) {
						break;
					}
				}
			} else {
				$this->validateFile($value);
			}
		}
		return parent::validate();
	}

	protected function validateFile(array $file) : void {
		if($file['error'] != UPLOAD_ERR_OK) {
			if($file['error'] == UPLOAD_ERR_NO_FILE) {
				$this->setValue(array());
				return;
			} else if(in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE])) {
				$this->valid = false;
				$this->error = 'File is too big';
			} else if(in_array($file['error'], [UPLOAD_ERR_NO_FILE, UPLOAD_ERR_PARTIAL])) {
				$this->valid = false;
				$this->error = 'File did not upload successfully';
			} else {
				http_error(500);
			}
		} else if($accept = $this->getAttribute('accept')) {
			$allowed = array_map(
				fun('strtolower'),
				array_map(fun('trim'),
				explode(',', $accept)));
			$allowed = array_filter($allowed);
			if(($pos = strrpos($file['name'], '.')) !== false) {
				$ext = substr($file['name'], $pos);
			} else {
				$ext = '';
			}
			if(in_array(strtolower($ext), $allowed)) {
				return;
			}
			// get mime type
			if(file_exists($file['tmp_name'])) {
				$mime = get_mime_type($file['tmp_name']);
			} else {
				$mime = $file['type'];
			}
			$mime = explode(';', $mime)[0];
			if(in_array($mime, $allowed)) {
				return;
			}
			// check for image/*, audio/* or video/*
			list($base, ) = explode('/', $mime, 2);
			if(in_array($base, ['video', 'audio', 'image']) && in_array($base . '/*', $allowed)) {
				return;
			}
			$_FILES[$this->getAttribute('name')]['type'] = $mime;
			$this->valid = false;
			$this->error = "Files of type '$mime' are not allowed.";
		}
	}

	public function getValue() : ?array {
		$value = $this->getAttribute('value');
		if(is_array($value) && $value) {
			if(is_array($value['error'])) {
				$values = array_filter($value['error'], function($v) {
					return $v != UPLOAD_ERR_OK;
				});
				if($values) {
					return null;
				}
			} else if($value['error'] != UPLOAD_ERR_OK) {
				return null;
			}
			return $value;
		}
		return [];
	}
}
