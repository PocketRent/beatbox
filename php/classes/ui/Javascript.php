<?hh

final class :bb:js extends :bb:base {
	attribute
		string dev_path @required,
		ConstVector dev @required,
		string live;

	public function compose() : :x:element {
		if(in_live() && ($path = (string)$this->getAttribute('live'))) {
			if(file_exists(BASE_DOC_DIR . '/' . $path)) {
				$path .= '?m=' . filemtime(BASE_DOC_DIR . '/'. $path);
			}
			$root = <script
				type="text/javascript"
				src={$path}
			/>;
		} else {
			$root = <x:frag />;
			$base = in_dev() ? BASE_DIR : BASE_DOC_DIR;
			$dv = (string)$this->getAttribute('dev_path') . '/';
			$dev = $this->getAttribute('dev');
			assert($dev instanceof ConstVector);
			foreach($dev as $path) {
				$path = $dv . $path;
				if(file_exists($base . '/' . $path)) {
					$path .= '?m=' . filemtime($base . '/'. $path);
				}
				$root->appendChild(<script
					type="text/javascript"
					src={$path}
				/>);
			}
		}
		return $root;
	}
}
