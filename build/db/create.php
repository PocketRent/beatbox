<?hh

function createHelp() : void {
	global $exe;
	echo <<<HELP
Usage: $exe create [options] <source-directory>

  Options:
    --no-create-db       Do not attempt to create database.
    --ignore-buildfiles  Ignore .build files and use run all scripts
	--buildfile-dir      Directory to place and search for .build files, default
                         is <source-directory>/build
HELP;
}

function doCreate(Vector<string> $args) : void {
	global $verbose;
	$verbose = false;
	// Info for creation
	$info = parse_common_args($args);
	$info['create_db'] = true;
	$info['ignore_buildfiles'] = false;
	$info['buildfile_dir'] = null;

	$positionals = Vector {};

	$iter = $args->getIterator();
	$iter->rewind();

	$getNext = function(string $opt) : string use ($iter) {
		$iter->next();
		if (!$iter->valid()) command_fail("Expected value for $opt");
		return $iter->current();
	};

	// Parse options
	while ($iter->valid()) {
		$arg = $iter->current();
		switch ($arg) {
		case "--no-create-db":
			$info['create_db'] = false;
			break;
		case "--ignore-buildfiles":
			$info['ignore_buildfiles'] = true;
			break;
		case "--buildfile-dir":
			$info['buildfile_dir'] = $getNext('buildfile-dir');
			break;
		default:
			if ($arg[0] == '-') {
				echo " Warning: Unknown flag '$arg'\n";
			} else {
				$positionals->add($arg);
			}
		}

		$iter->next();
	}

	if ($positionals->count() < 1) command_fail("Required source file directory");

	$src_dir = $positionals[0];
	if ($info['buildfile_dir'] === null) $info['buildfile_dir'] = "$src_dir/build";

	if (!file_exists($info['buildfile_dir'])) {
		vprint("Creating build directory");
		if (!mkdir($info['buildfile_dir'], 0777, true)) {
			command_fail("Could not create destination directory '".$info['buildfile_dir']."'");
		}
	}

	$conn = do_connect($info);

	$files = get_db_files($src_dir, $info['buildfile_dir'], $info['ignore_buildfiles']);

	// Filter out create files that have matching build files
	// Make a set with all the names of the .build files we have
	$builds = Set::fromItems($files->filter(function (DBFile $info) : bool {
		return $info->type == DBFile::TYPE_BUILD; // Filter only .build files
	})->map(function (DBFile $info) : string {
		return $info->name; // Map to the name
	}));

	$files = Vector::fromItems($files->filter(function (DBFile $info) : bool use ($builds) {
		// Filter out create files that exist in the .build set
		return $info->type != DBFile::TYPE_CREATE || !($builds->contains($info->name));
	}));

	// Sort the vector
	usort($files, function (DBFile $a, DBFile $b) : int { return $a->compareTo($b); });

	echo "Processing..."; vprint("");
	run_sql(DBFile::TYPE_PRE_SQL, $conn, $files);
	create_tables($conn, $info['buildfile_dir'], $files);
	alter_and_migrate($conn, $info['buildfile_dir'], $files);
	run_sql(DBFile::TYPE_POST_SQL, $conn, $files);
	echo "Done!\n";
}

class DBFile {
	const TYPE_BUILD	 = 0;
	const TYPE_PRE_SQL   = 1;
	const TYPE_CREATE	 = 2;
	const TYPE_ALTER	 = 3;
	const TYPE_MIGRATE   = 4;
	const TYPE_POST_SQL  = 5;
	const TYPE_UNKNOWN   = 255;

	public ?string $path = null;
	public ?string $name = null;

	public int $type = DBFile::TYPE_UNKNOWN;
	public int $priority = PHP_INT_MAX;
	public int $timestamp = 0;

	public function __construct(string $path) {
		$this->path = $path;
		$filename = basename($path);

		$re = '#^'. // Start
			  '((?<priority>(?i)[0-9]+)\.)?'. //Priority
			  '(?<name>[a-zA-Z][\w\d_-]*)'. //Name
			  '(\.(?<method>[a-zA-Z][\w\d_-]*))?'. //Method
			  '(\.(?<timestamp>\d{10,11}))?'. //Timestamp
			  '(\.(?<extension>.*))?'. // Extension
			  '$#'; // End

		$ret = preg_match($re, $filename, $matches);
		if ($ret == 1) {
			if (isset($matches['name'])) {
				$this->name = $matches['name'];
			} else {
				command_fail("File '$filename' has no name!");
			}

			$method = isset($matches['method']) ?
				strtolower($matches['method']) : null;
			$ext = isset($matches['extension']) ?
				strtolower($matches['extension']) : null;
			if ($ext === null && $method !== null) {
				$ext = $method; $method = null;
			}
			if(isset($matches['priority'])) {
				$this->priority = (int)$matches['priority'];
			}

			switch ($ext) {
			case "sql":
				if ($method == "create") {
					$this->type = DBFile::TYPE_CREATE;
				} else if ($method == "alter") {
					if (isset($matches['timestamp'])) {
						$this->type = DBFile::TYPE_ALTER;
						$this->timestamp = (int)$matches['timestamp'];
					} else {
						fwrite(STDERR, "Warning: ALTER file '%s' does not have a timestamp\n",
							$this->path);
					}
				} else if ($method == "pre") {
					$this->type = DBFile::TYPE_PRE_SQL;
				} else if ($method == "post"){
					$this->type = DBFile::TYPE_POST_SQL;
				}
				break;
			case "build":
				$this->type = DBFile::TYPE_BUILD;
				if (is_readable($this->path)) {
					$this->timestamp = (int)file_get_contents($this->path);
				} else {
					command_fail("Cannot read contents of BUILD file '".$this->path."'");
				}
				// Build files need to be writable
				if (!is_writable($this->path)) {
					command_fail("Cannot write to BUILD file '{$this->path}'");
				}
				break;
			default:
				if (is_executable($this->path)) {
					if (isset($matches['timestamp'])) {
						$this->type = DBFile::TYPE_MIGRATE;
						$this->timestamp = (int)$matches['timestamp'];
					}
				}
			}
		} else if ($res === false) {
			command_fail("Failed parsing name");
		}

		if ($this->timestamp != 0 && $this->timestamp > time()) {
			fprintf(STDERR,
				"Warning, timestamp associated with file '%s' (%s) is in the future!\n",
				$path, date('Y-m-d H:i:s', $this->timestamp));
		}
	}

	public function isValid(): bool {
		return $this->type != DBFile::TYPE_UNKNOWN && $this->name !== null && $this->path !== null;
	}

	public function isSQL(): bool {
		switch ($this->type) {
		case DBFile::TYPE_PRE_SQL:
		case DBFile::TYPE_POST_SQL:
		case DBFile::TYPE_CREATE:
		case DBFile::TYPE_ALTER:
			return true;
		default:
			return false;
		}
	}

	public function isEXE(): bool {
		return $this->type == DBFile::TYPE_MIGRATE;
	}

	public function compareTo(DBFile $b): int {
		if ($this->isValid() != $b->isValid()) {
			return $this->isValid() ? 1 : -1;
		}

		// ALTER and MIGRATE scripts need to be interwoven since migrate scripts may
		// rely on previous alter scripts and vice versa.
		if (($this->type == DBFile::TYPE_ALTER || $this->type == DBFile::TYPE_MIGRATE)
			&& ($b->type == DBFile::TYPE_ALTER || $b->type == DBFile::TYPE_MIGRATE)) {
			if ($this->timestamp < $b->timestamp) return -1;
			elseif ($this->timestamp > $b->timestamp) return 1;
			else return strcmp($this->name, $b->name);
		}

		if ($this->type < $b->type) return -1;
		elseif ($this->type > $b->type) return 1;

		if ($this->priority == -1) {
			if ($b->priority != $this->priority) {
				return -1;
			}
		} else {
			if($this->priority < $b->priority) return -1;
			elseif ($this->priority > $b->priority) return 1;
		}

		return strcmp($this->name, $b->name);
	}
}

function get_db_files(string $src_dir, string $buildfile_dir, bool $ignore_buildfiles) : Vector<string> {
	$files = Vector<string> {};

	vprint("Searching for files...");

	list($sqls, $exes) = get_db_files_r($src_dir, $files);
	vprint("Found $sqls SQL files and $exes executable files");

	if (!$ignore_buildfiles) {
		get_db_files_r($buildfile_dir, $files, true);
	}

	return $files;
}

function get_db_files_r(string $dirname, Vector<DBFile> &$files, bool $buildfiles=false) : Pair<int, int> {
	$dir = dir($dirname);
	if (!$dir) {
		command_fail("Unable to open directory '$dirname'");
	}

	vprint("Checking directory ".$dir->path);

	$sqls = 0;
	$exes = 0;

	while (($path = $dir->read()) !== false) {
		if ($path[0] == '.') {
			continue;
		}
		$fullpath = $dir->path.'/'.$path;

		if (is_dir($fullpath)) {
			list($s, $e) = get_db_files_r($fullpath, $files);
			$sqls += $s;
			$exes += $e;
		} else {
			$db_file = new DBFile($fullpath);
			if ($db_file->isValid() && ($buildfiles == ($db_file->type == DBFile::TYPE_BUILD))) {
				$files->add($db_file);
				if ($db_file->isSQL()) {
					$sqls++;
				} else if ($db_file->isEXE()) {
					$exes++;
				}
			} else {
				vprint("Skipping file $path...");
			}
		}
	}

	return Pair<int, int> {$sqls, $exes};
}

function run_sql(int $type, resource $conn, Vector<DBFile> $files) : void {
	$scripts = $files->filter(function (DBFile $info) : bool use ($type) {
		return $info->type == $type;
	});

	foreach ($scripts as $s) {
		vprint("Running SQL script '{$s->path}'");
		if (!is_readable($s->path)) command_fail("Cannot read SQL file '{$s->path}'");
		$sql = file_get_contents($s->path);
		if (strlen($sql) == 0) {
			fprintf(STDERR, "Warning: '%s' is empty\n", $s->path);
			continue;
		}
		$res = @pg_query($conn, $sql);
		if (!$res) {
			fwrite(STDERR, "  Failed! (".pg_last_error($conn).")\n");
		} else if (pg_result_status($res) == PGSQL_FATAL_ERROR) {
			fwrite(STDERR, "  Failed! (".
				pg_result_error_field($res, PGSQL_DIAG_MESSAGE_PRIMARY).")\n");
		}
	}
}

function create_tables(resource $conn, string $buildfile_dir, Vector<DBFile> $files) : void {
	$dbs = 0;
	$count = 0;
	$errors = 0;

	$creates = $files->filter(function (DBFile $info) : bool use (&$dbs) {
		if ($info->type == DBFile::TYPE_CREATE) {
			$dbs++; return true;
		}
		return false;
	});

	if ($dbs) {
		vprint("Creating $dbs tables...");
		foreach ($creates as $info) {
			vprint("  Creating ".$info->name);
			if (!is_readable($info->path))
				command_fail("Cannot read CREATE file '{$info->path}'");
			$sql = file_get_contents($info->path);
			if (strlen($sql) == 0) {
				fprintf(STDERR, "Warning: '%s' is empty\n", $info->path);
				$errors++;
				continue;
			}
			$res = @pg_query($conn, $sql);
			if (!$res) {
				fwrite(STDERR, "	Failed! (".pg_last_error($conn).")\n");
				$errors++;
			} else if (pg_result_status($res) == PGSQL_FATAL_ERROR) {
				fwrite(STDERR, "	Failed! (".
					pg_result_error_field($res, PGSQL_DIAG_MESSAGE_PRIMARY).")\n");
				$errors++;
			} else {
				$buildfile = $buildfile_dir.'/'.$info->name.'.build';
				touch($buildfile);
				$count++;
			}
		}

		if ($count) {
			vprint("Created $count tables ($errors errors)");
		} else {
			vprint("No tables created");
		}
	} else {
		vprint("No new tables to create");
	}
}

function alter_and_migrate(resource $conn, string $buildfile_dir, Vector<DBFile> $files) : void {
	$nscripts = 0;
	$count = 0;
	$skipped = 0;
	$errors = Set {};

	$builds = Map::fromItems($files->filter(function (DBFile $info): bool {
		return $info->type == DBFile::TYPE_BUILD;
	})->map(function (DBFile $info): Pair<string,int> {
		return Pair { $info->name, $info->timestamp };
	}));

	$alters = $files->filter(function (DBFile $info): bool use (&$nscripts, $builds, $buildfile_dir) {
		if ($info->type == DBFile::TYPE_ALTER ||
			$info->type == DBFile::TYPE_MIGRATE) {

			if ($builds->contains($info->name)) {
				if ($builds[$info->name] < $info->timestamp) {
					$nscripts++;
					return true;
				}
			} else if(file_exists($buildfile_dir.'/'.$info->name.'.build')) {
				$nscripts++;
				return true;
			}
		}
		return false;
	});

	if ($nscripts) {
		vprint("Found $nscripts applicable ALTER and MIGRATE scripts");
		foreach ($alters as $script) {
			if ($errors->contains($script->name)) {
				$skipped++;
				continue;
			}
			vprint("  Applying script {$script->path}");
			if ($script->type == DBFile::TYPE_ALTER) {
				if (!is_readable($script->path))
					command_fail("Cannot read ALTER file '{$script->path}'");
				$sql = file_get_contents($script->path);
				if (strlen($sql) == 0) {
					fprintf(STDERR, "	Warning: file '%s' is empty\n", $script->path);
					vprint("	Encountered error. Skipping rest of files for {$script->name}");
					$errors->add($script->name);
					continue;
				}

				$res = @pg_query($conn, $sql);
				if (!$res) {
					fwrite(STDERR, "	SQL Query Failed (".pg_last_error($conn)
						.") Skipping rest of files for {$script->name}\n");
					$errors->add($script->name);
				} else if (pg_result_status($res) == PGSQL_FATAL_ERROR) {
					fwrite(STDERR, "	SQL query Failed (".
						pg_result_error_field($res, PGSQL_DIAG_MESSAGE_PRIMARY)
						.") Skipping rest of files for {$script->name}\n");
					$errors->add($script->name);
				} else {
					$buildfile = $buildfile_dir.'/'.$script->name.'.build';
					file_put_contents($buildfile, $script->timestamp."\n");
					$count++;
				}
			} else if ($script->type == DBFile::TYPE_MIGRATE) {
				$status;
				system($script->path, $status);
				if ($status != 0) {
					fwrite(STDERR, "	Encountered error. Skipping rest of files for {$script->name}\n");
					$errors->add($script->name);
				} else {
					$buildfile = $buildfile_dir.'/'.$script->name.'.build';
					file_put_contents($buildfile, $script->timestamp."\n");
					$count++;
				}
			}
		}
		vprint("Finished applying scripts. $count success, {$errors->count()} errors, $skipped skipped");
	} else {
		vprint("No applicable ALTER or MIGRATE scripts found");
	}
}
