<?hh

final class :bb:css extends :bb:base {
	attribute
		string media = 'all',
		string dev @required,
		string live;

	public function compose() : :link {
		if(in_dev()) {
			$path = (string)$this->getAttribute('dev');
			$base = BASE_DIR;
		} else {
			$path = (string)$this->getAttribute('live');
			$base = BASE_DOC_DIR;
			if(!$path) {
				$path = (string)$this->getAttribute('dev');
				$base = BASE_DIR;
			} else {
				$check = BASE_DOC_DIR . '/' . $path;
				if(!realpath($check)) {
					$copyFrom = (string)$this->getAttribute('dev');
					// Dev is relative to BASE_DIR, rather than BASE_DOC_DIR
					copy(BASE_DIR . '/'. $copyFrom, $check);
				}
			}
		}

		if(file_exists($base . '/' . $path)) {
			$path .= '?m=' . filemtime($base . '/'. $path);
		}

		return <link
			href={$path}
			media={$this->getAttribute('media')}
			rel="stylesheet"
			type="text/css"
		/>;
	}
}
