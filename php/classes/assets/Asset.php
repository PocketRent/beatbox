<?hh

namespace beatbox;

class Asset {

	/**
	 * The unique ID for this asset
	 */
	private $id = null;
	/**
	 * The name of this asset (normally the original
	 * filename)
	 */
	private $name;
	/**
	 * The mime type of this asset
	 */
	private $mime;

	/**
	 * The source path for this file
	 */
	private $source_path;

	/**
	 * Store a file in the assets store
	 *
	 * $file should be an array similar to one from $_FILES,
	 * meaning it should have at least 'name', and 'tmp_name'
	 * keys. If there is an 'error' key, then it must be equal
	 * to UPLOAD_ERR_OK
	 *
	 * 'tmp_name' must be an uploaded file
	 *
	 */
	public static function store(\ConstMapAccess $file) : Asset {
		if (!isset($file['name']) || !isset($file['tmp_name'])) {
			throw new \InvalidArgumentException("\$file must have 'name' and 'tmp_name' keys");
		}

		assert(!isset($file['error']) || $file['error'] == UPLOAD_ERR_OK);

		$name = $file['name'];
		$source = $file['tmp_name'];


		if (!is_uploaded_file($source)) {
			throw new \InvalidArgumentException("Source file must be an uploaded file");
		}

		// Get the mime type of the file (NEVER TRUST THE CLIENT)
		$mime = get_mime_type($source);

		$asset = new self;
		$asset->setName($name);
		$asset->setMIME($mime);
		$asset->loadSourceFile($source);
		$asset->write();

		send_event("asset::store", $name, $mime);

		return $asset;
	}

	/**
	 * Load a file from the assets store
	 *
	 * @return Asset or null, if it doesn't exist
	 */
	public static function load(\mixed $id) : Asset? {
		$conn = orm\Connection::get();

		$eid = $conn->escapeValue($id);
		$query = "SELECT * FROM \"Asset\" WHERE \"ID\"=$eid LIMIT 1";
		$res = $conn->queryBlock($query);
		if ($res->numRows() == 0) {
			send_event("asset::db-miss", $id);
			return null;
		} else {
			$row = $res->nthRow(0);
			$asset = new self;
			$asset->id = $row['ID'];
			$asset->name = $row['Name'];
			$asset->source_path = $row['SourcePath'];
			$asset->mime = $row['Type'];

			return $asset;
		}
	}

	/**
	 * Sets the name of the file
	 */
	public function setName(\string $name) : \void {
		$this->name = $name;
	}

	/**
	 * Gets the name of the file
	 */
	public function getName() : \string {
		return $this->name;
	}

	/**
	 * Sets the MIME type of the file
	 */
	public function setMIME(\string $mime) : \void {
		$this->mime = $mime;
	}

	/**
	 * Gets the MIME type of the file
	 */
	public function getMIME() : \string {
		return $this->mime;
	}

	/**
	 * Returns the unique ID for the Asset.
	 * If there is no ID, this returns null.
	 */
	public function id() {
		return $this->id;
	}

	/**
	 * Loads a file from the given source path, moving it from
	 * the original location.
	 */
	public function loadSourceFile(\string $source) : \void {
		if (!is_file($source)) {
			throw new \InvalidArgumentException("'$source' is a not a file");
		}
		if (!is_writable($source)) {
			throw new \Exception("'$source' is not writable");
		}

		// If the assets folder doesn't exist, create it
		if (!file_exists(ASSET_PATH)) {
			mkdir(ASSET_PATH, 0777, true);
			send_event("asset::make-asset-dir", ASSET_PATH);
		}

		do {
			$filename = generate_random_token();
		} while(file_exists(ASSET_PATH . '/' . $filename));

		rename($source, ASSET_PATH.'/'.$filename);
		// Set ownership, read/write for owner only
		chown($filename, 0600);

		$this->source_path = $filename;

		send_event("asset::load-source");
	}

	/**
	 * Writes the Asset information to the database
	 */
	public function write() : \void {
		$conn = orm\Connection::get();

		$name = $conn->escapeValue($this->name);
		$type = $conn->escapeValue($this->mime);
		$source_path = $conn->escapeValue($this->source_path);

		if ($this->id === null) {

			$query = "INSERT INTO \"Asset\"
				(\"Name\", \"Type\", \"SourcePath\") VALUES
				($name, $type, $source_path) RETURNING \"ID\"";

			$res = $conn->queryBlock($query);
			assert($res->numRows() == 1);
			$this->id = $res->nthRow(0)['ID'];
		} else {
			$id = $conn->escapeValue($this->id);
			$query = "UPDATE \"Asset\" SET \"Name\"=$name, \"Type\"=$type,
				\"SourcePath\"=$source_path WHERE \"ID\"=$id";
			$res = $conn->queryBlock($query);
			assert($res->numRows() == 1);
		}
	}

	public function delete() : \void {
		$conn = orm\Connection::get();

		if ($this->id !== null) {
			send_event("asset::delete", $this->id);
			$query = "DELETE FROM \"Asset\" WHERE \"ID\"=".((int)$this->id);
			$conn->query($query);
			$this->id = null;
			if ($this->source_path) {
				$filepath = ASSET_PATH.'/'.$this->source_path;
				if (file_exists($filepath)) {
					unlink($filepath);
				}
			}
		}
	}

	/**
	 * Returns a URI for accessing the file, or the fallback URI if one cannot be provided
	 */
	public function getURI(\string $fallback = '') : \string {
		if ($this->source_path) {
			$filepath = ASSET_PATH.'/'.$this->source_path;
			if (file_exists($filepath) && is_file($filepath)) {
				// Currently hard-coded, should be changed later.
				return $filepath;
			} else {
				send_event("asset::file-miss", $filepath);
				$this->delete();
			}
		}

		return $fallback;
	}

	/**
	 * Returns an access path for the file so operations that need a physical
	 * file locally can work.
	 */
	public function getAccessPath() : \string {
		return ASSET_PATH.'/'.$this->source_path;
	}

	/**
	 * Creates an Icon for this file
	 *
	 * If $thumnail is false, then no thumbnail is created, even if
	 * the asset can be thumbnailed
	 *
	 */
	public function icon(\bool $thumnail = true) : :bb:icon {
		if ($thumnail && $this->thumbnailable()) {
			$icon = <bb:thumbnail />;
			$icon->setAsset($this);
			return $icon;
		} else {
			return <bb:icon src={$this->iconName()} />;
		}
	}

	/**
	 * Returns whether or not this asset is an image
	 */
	public function isImage() : \bool {
		return substr($this->getMIME(), 0, 5) == 'image';
	}

	/**
	 * Returns whether or not this asset can be turned into a thumbnail.
	 */
	public function thumbnailable() : \bool {
		// TODO: Thumbnail generation when we have Imagick
		return false;
	}

	/**
	 * Returns the appropriate icon name for this asset
	 */
	public function iconName() : \string {
		$mime = $this>getMIME();
		if (substr($mime, 0, 5) == 'image') {
			return 'image';
		}
		switch($mime) {
		default: return 'file';
		case 'application/pdf': return 'pdf';
		}
	}

	public function __clone() : \void {
		$this->id = null;
	}

}
