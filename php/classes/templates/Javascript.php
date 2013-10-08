<?php

final class :bb:js extends :bb:base {
	attribute
		string dev_path @required,
		Vector dev @required,
		string live;

	public function compose() {
		if(in_live() && ($path = $this->getAttribute('live'))) {
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
			$dv = $this->getAttribute('dev_path') . '/';
			foreach($this->getAttribute('dev') as $path) {
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
