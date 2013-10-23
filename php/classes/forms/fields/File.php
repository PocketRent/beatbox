<?hh

class :bb:form:file extends :bb:form:field {
	attribute
		:input,
		array value;

	protected $type = 'file';

	public function validate() {
		$value = $this->getAttribute('value');
		if($this->valid && $value) {
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

	protected function validateFile(array $file) {
		if($file['error'] != UPLOAD_ERR_OK) {
			if(in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE])) {
				$this->valid = false;
				$this->error = 'File is too big';
			} elseif(in_array($file['error'], [UPLOAD_ERR_NO_FILE, UPLOAD_ERR_PARTIAL])) {
				$this->valid = false;
				$this->error = 'File did not upload successfully';
			} else {
				http_error(500);
			}
		} elseif($accept = $this->getAttribute('accept')) {
			$allowed = array_filter(array_map('strtolower', array_map('trim', explode(',', $accept))));
			if(($pos = strrpos($file['name'], '.')) !== false) {
				$ext = substr($file['name'], $pos);
			} else {
				$ext = '';
			}
			if(in_array(strtolower($ext), $allowed)) {
				return;
			}
			// get mime type
			$mime = get_mime_type($file['tmp_name']);
			if(in_array($mime, $allowed)) {
				return;
			}
			// check for image/*, audio/* or video/*
			list($base, ) = explode('/', $mime, 2);
			if(in_array($base, ['video', 'audio', 'image']) && in_array($base . '/*', $allowed)) {
				return;
			}
			$this->valid = false;
			$this->error = 'Files of that type are not allowed.';
		}
	}

	public function getValue() {
		$value = $this->getAttribute('value');
		if($value) {
			if(is_array($value['error'])) {
				$values = array_filter($value['error'], function($v) { return $v != UPLOAD_ERR_OK;});
				if($values) {
					return null;
				}
			} elseif($value['error'] != UPLOAD_ERR_OK) {
				return null;
			}
		}
		return $value;
	}
}
