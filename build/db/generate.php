<?hh

function generateHelp() : void {
	global $exe;
	echo <<<HELP
Usage: $exe generate [options] <dest-directory>

  Options:
    -n, --namespace NS     Table namespace to scan. Uses 'public' by default.
    -P, --php-ns NS        PHP namespace for the generated code.
    --exclude EXCLUDE      Exclude table/columns matching EXCLUDE pattern.
                           This option can be provided multiple times.
    --exclude-from FILE    Exclude tables/columns matching patterns in FILE.
                           FILE should contain one pattern per line. Empty
                           lines and lines starting with '#' are ignored.

  Exclude patterns:
    Exclude patterns are used to exlude tables and columns from the code
    generator. The format of an exclude pattern is as follows:

        <table-name>[:{<column-name>,...}]

    For example: 'Person:Accounting,Special' would exclude the 'Accounting'
    and 'Special' columns in the 'Person' table from having code generated for
    them.

    If there are no columns, then the entire table is excluded. If the
    exclusion pattern causes all columns in a table to be excluded, then the
    entire table is excluded.
HELP;

}

function doGenerate(Vector<string> $args) : void {
	global $verbose;
	$verbose = false;

	$db_info = parse_common_args($args);

	$excludes = Map<string,ExcludePattern> {};
	$positionals = Vector<string> {};
	$ns = "public";
	$php_ns = null;

	$iter = $args->getIterator();
	$iter->rewind();

	$getNext = function($opt) use ($iter) {
		$iter->next();
		if (!$iter->valid()) command_fail("Expected value for $opt");
		return $iter->current();
	};

	// Parse options
	while ($iter->valid()) {
		$arg = $iter->current();
		switch ($arg) {
		case "--exclude":
			$pattern = new ExcludePattern($getNext("exclude"));
			if ($pattern->isValid()) {
				$excludes[$pattern->table] = $pattern;
			} else {
				command_fail("Invalid Pattern '".$pattern->src."'");
			}
			break;
		case "--exclude-from": //Read patterns from the file
			$file = $getNext("exclude-from");
			$excludes->setAll(ExcludePattern::fromFile($file));
			break;
		case "-n":
		case "--namespace":
			$ns = $getNext("namespace");
			break;
		case "-P":
		case "--php-ns":
			$php_ns = $getNext("php-ns");
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

	if ($positionals->count() < 1) command_fail("Required destination directory");

	$directory = $positionals[0];
	if (!file_exists($directory)) {
		vprint("Creating destination directory");
		if (!mkdir($directory, 0777, true)) {
			command_fail("Could not create destination directory '$destination'");
		}
	}

	$exclude_file = __DIR__.'/../../db/db.exclude';
	if (file_exists($exclude_file)) {
		$excludes->setAll(ExcludePattern::fromFile($exclude_file));
	}

	$conn = do_connect($db_info);

	$type_dict = new TypeDict($conn);

	$tables = load_tables($conn, $ns, $excludes);
	foreach ($tables as $t) { $t->loadComments($conn); }

	$constraints = load_constraints($conn);

	pg_close($conn); unset($conn);

	resolve_fk_constraints($tables, $constraints);
	generate_php($tables, $type_dict, $directory, $php_ns);
}

class ExcludePattern {

	public $table = null;
	public $columns = Vector<string> {};

	public $src;

	public function __construct(string $pat) {
		$this->src = trim($pat);

		$parts = explode(':', $this->src, 2);

		$table = trim($parts[0]);
		if (!preg_match('#^[\d\w_]+$#', $table)) {
			return;
		}

		$this->table = $table;

		if (count($parts) > 1) {
			$cols = Vector::fromArray(explode(',', $parts[1]));
			$cols = Vector::fromItems($cols->map(function (string $col) : string {
				$col = trim($col);
				if (preg_match('#^[\d\w_]*$#', $col)) {
					return $col;
				} else {
					vprint("Ignoring invalid column name: '$col'");
					return "";
				}
			})->filter(function (string $col) : bool {
				return strlen($col) > 0;
			}));

			$this->columns = $cols;
		}
	}

	public static function fromFile(string $file) : Map<string, ExcludePattern> {
		$excludes = Map {};
		if (file_exists($file) && is_readable($file)) {
			$pats = file_get_contents($file);
			if (strlen($pats) == 0) {
				fprintf(STDERR, "Warning: exclusion file '$file' is empty\n");
				return Map {};
			}
			$pats = Vector::fromArray(explode("\n", $pats));
			// Filter out empty lines and comments
			$pats_iter = $pats->filter(function (string $pat) : bool {
				return strlen($pat) > 0 && $pat[0] != '#';
			})->map(function (string $pat) : string { // Strip off a trailing comment
				$hp = strpos($pat, '#');
				return $hp === false ? $pat : substr($pat, 0, $hp);
			});
			foreach ($pats_iter as $pat) {
				$pat_obj = new ExcludePattern($pat);
				if ($pat_obj->isValid()) {
					$excludes[$pat_obj->table] = $pat_obj;
				} else {
					command_fail("Invalid Pattern '".$pat_obj->src."'");
				}
			}
		} else {
			command_fail("Cannot read exclusion file '$file'");
		}

		return $excludes;
	}

	public function isValid(): bool {
		return $this->table !== null;
	}
}

class PGType {
	const T_BASE            = 'b';
	const T_COMPOSITE       = 'c';
	const T_DOMAIN          = 'd';
	const T_ENUM            = 'e';
	const T_PSEUDO          = 'p';

	const TCAT_ARRAY        = 'A';
	const TCAT_BOOL         = 'B';
	const TCAT_COMPOSITE    = 'C';
	const TCAT_DATETIME     = 'D';
	const TCAT_ENUM         = 'E';
	const TCAT_GEOM         = 'G';
	const TCAT_NET_ADDR     = 'I';
	const TCAT_NUMERIC      = 'N';
	const TCAT_PSEUDO       = 'P';
	const TCAT_STRING       = 'S';
	const TCAT_TIMESPAN     = 'T';
	const TCAT_USER_DEF     = 'U';
	const TCAT_BITSTRING    = 'V';
	const TCAT_UNKNOWN      = 'X';

	public $name;
	public $oid;
	public $delim = ',';

	public $type;
	public $description = "";

	public $type_cat = 'X';
	public $type_pref = false;

	public $sub_type = null;

	public $elements = null;

	public $rel_id = 0;

	public $type_dict = null;

	public $written = false;

	public function __construct($row, $tydict) {
		$this->oid = (int)$row['oid'];
		$this->name = $row['typname'];
		$this->delim = $row['typdelim'];
		$this->type = $row['typtype'];
		$this->type_cat = $row['typcategory'];
		$this->type_pref = $row['typispreferred'] == 't';

		if ($this->type_cat == PGType::TCAT_ARRAY) {
			$this->sub_type = (int)$row['typelem'];
		} else if ($this->type == PGType::T_DOMAIN) {
			$this->sub_type = (int)$row['typbasetype'];
		}

		$this->rel_id = (int)$row['typrelid'];

		$this->description = $row['description'];

		$this->type_dict = $tydict;
	}

	public function isSimple() : bool {
		return !($this->type_cat == PGType::TCAT_COMPOSITE ||
			$this->type_cat == PGType::TCAT_ARRAY);
	}

	public function needsElements(): bool {
		switch ($this->type) {
		case PGType::T_COMPOSITE:
		case PGType::T_ENUM:
			return $this->elements == null;
		default:
			return false;
		}
	}

	public function retrieveTypeElements($conn) {
		if ($this->needsElements()) {
			switch ($this->type) {
			case PGType::T_COMPOSITE:
				$q = pg_query_params($conn, "SELECT attname, atttypid
					FROM pg_attribute WHERE attisdropped=FALSE AND attrelid=$1 ORDER BY attnum ASC",
					[$this->rel_id]);
				if (!$q) {
					command_fail(pg_last_error($conn));
				}
				$this->elements = StableMap {};
				while($row = pg_fetch_assoc($q)) {
					$attr_type = $this->type_dict->typeByOid((int)$row['atttypid']);
					if ($attr_type === null) {
						command_fail("Could not find entry for composite type field ".$row['attname']);
					}
					$this->elements[$row['attname']] = $attr_type;
				}
				break;
			case PGType::T_ENUM:
				$q = pg_query_params($conn, "SELECT enumlabel
					FROM pg_enum WHERE enumtypid=$1 ORDER BY enumsortorder ASC",
					[$this->oid]);
				if (!$q) {
					command_fail(pg_last_error($conn));
				}
				$this->elements = Vector {};
				while($row = pg_fetch_assoc($q)) {
					$this->elements->add($row['enumlabel']);
				}
				break;
			default:
				die("WAT");
			}
		}
	}

	public function convertCode(CodeFile $f, string $dest, string $raw, bool $sub=false) {
		$tstring = $this->__toString();
		if ($tstring == '\int' && $this->type_cat == PGType::TCAT_NUMERIC) {
			$f->writeLine("$dest = (int)$raw;");
		} else if ($tstring == '\float') {
			$f->writeLine("$dest = (float)$raw;");
		} else if ($tstring == '\string') {
			if ($sub) {
				$f->writeLine("$dest = db_parse_string($raw);");
			} else {
				$f->writeLine("$dest = $raw;");
			}
		} else if ($tstring == '\bool') {
			$f->writeLine("$dest = $raw == 't';");
		} else if ($this->type_cat == PGType::TCAT_ARRAY) {
			$subtype = $this->type_dict->typeByOid($this->sub_type);
			$hash = substr(md5($dest.$raw.$this), 0, 6);

			$f->writeLine("\$__tmpArr_$hash = \db_parse_array('$subtype->delim', substr($raw,1,-1));");
			$f->writeLine("$dest = \Vector {};");
			$f->writeLine("{$dest}->reserve(count(\$__tmpArr_$hash));");
			$f->startBlock("foreach (\$__tmpArr_$hash as \$__elem_$hash)");
			$subtype->convertCode($f, "\$__convElem_$hash", "\$__elem_$hash", true);
			$f->writeLine("{$dest}->append(\$__convElem_$hash);");
			$f->endBlock();
		} else if ($this->type == PGType::T_COMPOSITE) {
			$f->writeLine("$dest = {$this->name}::fromString($raw);");
		} else if ($this->type_cat == PGType::TCAT_DATETIME) {
			$f->writeLine("$dest = new \\beatbox\\orm\\DateTimeType($raw);");
		} else {
			$f->writeLine("$dest = $raw;");
		}
	}

	public function needsWrite(): bool {
		if(($this->type_cat == PGType::TCAT_ARRAY || $this->type == PGType::T_DOMAIN) && $this->type_dict) {
			$subType = $this->type_dict->typeByOid($this->sub_type);
			return $subType && $subType->needsWrite();
		}
		return $this->type == PGType::T_COMPOSITE && !$this->written;
	}

	public function writeTo(CodeFile $file) {
		if($this->type_cat == PGType::TCAT_ARRAY || $this->type == PGType::T_DOMAIN) {
			return $this->type_dict->typeByOid($this->sub_type)->writeTo($file);
		}
		vprint("Generating type code for $this->name");

		if ($this->type == PGType::T_COMPOSITE) {
			$file->startBlock("class $this->name extends \\beatbox\\orm\\CompositeType");
			$file->writeLine();

			foreach ($this->elements as $name => $type) {
				$file->writeLine("private \$_$name; // $type");
			}
			$file->writeLine();

			$file->startBlock('public static function fromString($val): '.$this->name);

			$file->writeLine('if ($val === null) return 0;');
			$file->writeLine('$parts = db_parse_composite($val);');

			$file->startBlock("if (count(\$parts) != {$this->elements->count()})");
			$file->writeLine('throw new \beatbox\orm\InvalidValueException($val);');
			$file->endBlock();
			$file->writeLine();

			$file->writeLine("\$obj = new $this->name;");

			$i = 0;
			foreach ($this->elements as $name => $type) {
				$type->convertCode($file, "\$obj->_$name", "\$parts[$i]");
				$i++;
			}

			$file->writeLine();

			$file->writeLine("return \$obj;");

			$file->endBlock();

			$file->writeLine();

			$file->startBlock("public final function toDBString(\\beatbox\\orm\\Connection \$conn): string");

			if ($this->elements->count() == 1) {
				$file->writeLine('$str = "ROW(";');
			} else {
				$file->writeLine('$str = "(";');
			}
			$n = $this->elements->count() - 1;
			$i = 0;
			foreach ($this->elements as $name => $type) {
				$file->writeLine("\$str .= \$conn->escapeValue(\$this->_$name);");
				if ($i != $n)
					$file->writeLine("\$str .= ',';");
				$i++;
			}
			$file->writeLine('$str .= ")";');
			$file->writeLine('return $str;');

			$file->endBlock();

			$file->writeLine();

			foreach ($this->elements as $name => $type) {
				$file->startBlock("public function get$name()");
				$file->writeLine("return \$this->_$name;");
				$file->endBlock();

				$file->writeLine();

				$file->startBlock("public function set$name(\$val)");
				$file->writeLine("\$new_obj = clone \$this;");
				$file->writeLine("\$new_obj->_$name = \$val;");
				$file->writeLine("return \$new_obj;");
				$file->endBlock();

				$file->writeLine();
			}

			$file->startBlock("public function __toString(): string");
			$file->writeLine("\$str = \"$this->name {\\n\";");
			foreach ($this->elements as $name => $t) {
				$file->writeLine("\$str .= '    $name => ';");
				$file->writeLine("\$str .= \$this->_$name . \"\\n\";");
			}
			$file->writeLine('$str .= \'}\'; return $str;');

			$file->endBlock();
			$file->endBlock();
		}

		$this->written = true;
	}

	public function __toString(): string {
		if ($this->type == PGType::T_BASE) {
			if ($this->type_cat == PGType::TCAT_ARRAY) {
				return '\Vector';
			} else if ($this->type_cat == PGType::TCAT_NUMERIC) {
				if (substr($this->name, 0, 3) == 'int') {
					return '\int';
				} else {
					return '\float';
				}
			} else if ($this->type_cat == PGType::TCAT_BOOL) {
				return '\bool';
			} else if ($this->type_cat == PGType::TCAT_STRING) {
				return "\string";
			} else if ($this->type_cat == PGType::TCAT_DATETIME) {
				return "\\beatbox\\orm\\DateTimeType";
			}
		} else if ($this->type == PGType::T_DOMAIN) {
			return $this->type_dict->typeByOid($this->sub_type)->__toString();
		} else if ($this->type == PGType::T_ENUM) {
			return '';
		}
		return $this->name;
	}

	public static function categoryName($cat) {
		switch ($cat) {
		case PGType::TCAT_ARRAY:
			return 'array';
		case PGType::TCAT_BOOL:
			return 'bool';
		case PGType::TCAT_COMPOSITE:
			return 'composite';
		case PGType::TCAT_DATETIME:
			return 'datetime';
		case PGType::TCAT_ENUM:
			return 'enum';
		case PGType::TCAT_GEOM:
			return 'geometric';
		case PGType::TCAT_NET_ADDR:
			return 'network_address';
		case PGType::TCAT_NUMERIC:
			return 'numeric';
		case PGType::TCAT_PSEUDO:
			return 'pseudo';
		case PGType::TCAT_STRING:
			return 'string';
		case PGType::TCAT_TIMESPAN:
			return 'timespan';
		case PGType::TCAT_USER_DEF:
			return 'user_defined';
		case PGType::TCAT_BITSTRING:
			return 'bitstring';
		case PGType::TCAT_UNKNOWN:
			return 'unknown';
		default:
			command_fail("Unknown category given! ($cat)");
		}
	}
}

class TypeDict {

	private $types = Vector {};
	private $oid_map = Map {};
	private $name_map = Map {};


	public function __construct($conn) {
		vprint("Creating type dictionary");
		$q = pg_query($conn, "SELECT
			t.oid, t.typname, t.typdelim, t.typtype, t.typcategory, t.typispreferred,
			t.typelem, t.typbasetype, t.typrelid, d.description
			FROM pg_type t LEFT JOIN
			pg_description d ON (t.oid = d.objoid)
			WHERE typcategory != 'X';");

		if (pg_result_status($q) != PGSQL_TUPLES_OK) {
			command_fail(pg_result_error($q));
		}

		$needs_els = Vector {};

		while ($row = pg_fetch_assoc($q)) {
			$t = new PGType($row, $this);
			$this->types->add($t);
			$this->name_map[$t->name] = $t;
			$this->oid_map[$t->oid] = $t;
			if ($t->needsElements()) {
				$needs_els->add($t);
			}
		}

		if ($needs_els->count() > 0) {
			vprint("  Retrieving elements for {$needs_els->count()} types");
			foreach ($needs_els as $type) {
				$type->retrieveTypeElements($conn);
			}
		}

		vprint("Loaded {$this->types->count()} types from database");
	}

	public function typeByOid(int $oid): PGType {
		return isset($this->oid_map[$oid]) ? $this->oid_map[$oid] : null;
	}

	public function typeByName(string $name): PGType {
		return isset($this->name_map[$name]) ? $this->name_map[$name] : null;
	}
}

class Table {

	public $oid;
	public $name;
	public $columns = StableMap {};

	public $description = null;
	public $constraints = Map {};

	public $has_ones = Map {};
	public $has_manys = Map {};

	private $cols_sorted = false;

	public function __construct(string $name, int $oid) {
		$this->name = $name;
		$this->oid = $oid;
	}

	public function addColumn(array $col) {
		$this->columns[$col['column_name']] = new Column($col);
		$this->cols_sorted = false;
	}

	public function loadComments($conn) {
		vprint("Loading comments for {$this->name}");

		$q = pg_query_params($conn,
			"SELECT objsubid, description FROM pg_description WHERE objoid=$1", [$this->oid]);
		if (!$q) {
			command_fail(pg_last_error($conn));
		}

		$n = 0;
		while ($row = pg_fetch_row($q)) {
			list($idx, $desc) = $row;

			if ($idx == 0) {
				$this->description = $desc;
				$n++;
			} else if ($col = $this->getColumn($idx)) {
				$col->description = $desc;
				$n++;
			}
		}

		vprint("Loaded $n comments");
	}

	public function getColumn($idx) {
		$this->sortColumns();
		foreach ($this->columns as $col) {
			if ($col->position == $idx) return $col;
		}
		return null;
	}

	public function sortColumns() {
		if (!$this->cols_sorted) {
			uasort($this->columns, function (Column $a, Column $b): int {
				if ($a->position == $b->position) return 0;
				return $a->position < $b->position ? -1 : 1;
			});
			$this->cols_sorted = true;
		}
	}

	public function setPrimaryKey($cols) {
		foreach($cols as $col) {
			$this->columns[$col]->primary = true;
		}
	}

	public function primaryKeys(): Iterator {
		$this->sortColumns();
		return $this->columns->values()->filter(function ($val) {
			return $val->primary;
		});
	}

	public function addHasOne(string $name, string $table, Vector<string> $cols) {
		$this->has_ones[$name] = Pair { $table, $cols };
	}

	public function addHasMany(string $name, string $table, Vector<string> $cols) {
		$this->has_manys[$name] = Pair { $table, $cols };
	}

	public function hasOnes(): Iterator {
		$table_counts = Map{};
		foreach ($this->has_ones as $has_one) {
			if (!isset($table_counts[$has_one[0]])) {
				$table_counts[$has_one[0]] = 1;
			} else {
				$table_counts[$has_one[0]]++;
			}
		}

		return $this->has_ones->items()
			->map(function (Pair $pair): array use ($table_counts) {

				if ($table_counts[$pair[1][0]] == 1) {
					return [ $pair[1][0], $pair[1][0], $pair[1][1] ];
				} else {
					return [ $pair[0], $pair[1][0], $pair[1][1] ];
				}
			});
	}

	public function hasManys(): Iterator {
		$table_counts = Map{};
		foreach ($this->has_manys as $has_one) {
			if (!isset($table_counts[$has_one[0]])) {
				$table_counts[$has_one[0]] = 1;
			} else {
				$table_counts[$has_one[0]]++;
			}
		}

		return $this->has_manys->items()
			->map(function (Pair $pair): array use ($table_counts) {

				if ($table_counts[$pair[1][0]] == 1) {
					return [ $pair[1][0], $pair[1][0], $pair[1][1] ];
				} else {
					return [ $pair[0], $pair[1][0], $pair[1][1] ];
				}
			});
	}
}

class Column {
	public $name;

	public $data_type = null;
	public $udt_name = null;
	public $element_type = null;

	public $updatable;
	public $position;

	public $description = null;

	public $primary = false;

	public $nullable = false;

	public function __construct(array $data) {
		$this->name = $data['column_name'];
		$this->data_type = $data['data_type'];
		$this->udt_name = $data['udt_name'];
		if ($this->data_type == 'ARRAY') {
			$this->element_type = $data['element_type'];
		}
		$this->updatable = $data['updatable'];
		$this->position = (int)$data['ordinal_position'];
		$this->nullable = $data['is_nullable'] == 'YES';
	}

	public function getType(TypeDict $dict): PGType {
		return $dict->typeByName($this->udt_name);
	}
}

class Constraint {
	public $type;
	public $name;
	public $on_table; // Table this constraint is on
	public $to_table;

	public $columns = Vector {};

	public function __construct(string $type, string $name, string $on_table, $to_table) {
		$this->type = $type;
		$this->name = $name;
		$this->on_table = $on_table;
		$this->to_table = $to_table;
	}

	public function addColumn(Pair<string,string> $column) {
		$this->columns->add($column);
	}
}

function load_tables($conn, string $ns, Map<string,ExcludePattern> $excludes): Vector<Table> {
	vprint("Reading tables...");

	$ex_tables_it = $excludes->filter(function ($pat): bool {
		return $pat->columns->count() == 0;
	})->keys();

	$ex_cols_it = new AppendIterator;
	foreach ($excludes->filter(function($pat): bool {
		return $pat->columns->count() > 0;
	})->map(function ($pat): VectorIterator<string> {
		return $pat->columns->items();
	}) as $it) {
		$ex_cols_it->append($it);
	}

	$ex_tables = [];
	$ex_cols = [];

	foreach ($ex_tables_it as $table) {
		$ex_tables[] = pg_escape_literal($conn, $table);
	}
	foreach ($ex_cols_it as $col) {
		$ex_cols[] = pg_escape_literal($conn, $table);
	}

	$ex_tables_q = '';
	$ex_cols_q = '';

	if (count($ex_tables) > 0) {
		$ex_tables_q = 'AND c.table_name NOT IN ('.implode(',', $ex_tables).')';
	}

	if (count($ex_cols) > 0) {
		$ex_cols_q = 'AND c.column_name NOT IN ('.implode(',', $ex_cols).')';
	}

	$cols = pg_query_params($conn, "SELECT
		t.oid as table_oid,
		c.table_name, c.column_name, c.ordinal_position, c.data_type,
		c.udt_name, e.data_type AS element_type,
		c.is_nullable,
		bool_and(is_updatable='YES' OR
			((trg.tgtype & 20)::bool AND (trg.tgtype & 64)::bool)) as updatable FROM
		information_schema.columns c
		LEFT JOIN information_schema.element_types e
		ON ((c.table_catalog, c.table_schema, c.table_name, 'TABLE', c.dtd_identifier)
		= (e.object_catalog, e.object_schema, e.object_name, e.object_type, e.collection_type_identifier))
		LEFT JOIN pg_class t ON (t.relname = c.table_name)
		LEFT JOIN pg_trigger trg ON (t.oid = trg.tgrelid)
		WHERE c.table_schema = $1 $ex_tables_q $ex_cols_q
		GROUP BY c.table_name, c.column_name, t.oid, c.ordinal_position, c.data_type, c.udt_name, e.data_type, c.is_nullable
		ORDER BY t.oid, c.ordinal_position
	", [$ns]);

	if (!$cols) {
		command_fail(pg_last_error($conn));
	}

	$tables = Map {};

	while ($row = pg_fetch_assoc($cols)) {
		$table = null;
		if (isset($tables[$row['table_name']])) {
			$table = $tables[$row['table_name']];
		} else {
			vprint("  Found table \"{$row['table_name']}\"");
			$table = new Table($row['table_name'], (int)$row['table_oid']);
			$tables[$row['table_name']] = $table;
		}

		$table->addColumn($row);

	}

	vprint("Read {$tables->count()} tables from database");

	return Vector::fromItems($tables->values());
}

function load_constraints($conn): Vector<Constraint> {

	vprint("Loading Column Constraints");
	$fkc = pg_query_params($conn, "SELECT
		c.table_name as on_table, k2.table_name as to_table,
		k.column_name as local_column, k2.column_name as foreign_column,
		c.constraint_name, c.constraint_type FROM
		information_schema.table_constraints c LEFT JOIN
		information_schema.referential_constraints r ON(r.constraint_name = c.constraint_name) LEFT JOIN
		information_schema.key_column_usage k
		ON(c.table_name=k.table_name AND k.constraint_name=c.constraint_name) LEFT JOIN
		information_schema.key_column_usage k2
		ON(k.position_in_unique_constraint=k2.ordinal_position AND k2.constraint_name=r.unique_constraint_name)
		WHERE c.constraint_type IN ('PRIMARY KEY', 'FOREIGN KEY')
		ORDER BY c.constraint_name ASC", []);

	if (!$fkc) {
		command_fail(pg_last_error($conn));
	}

	$constraints = Map{};

	while ($row = pg_fetch_assoc($fkc)) {
		$name = $row['constraint_name'];
		$on_table = $row['on_table'];
		$to_table = $row['to_table'];
		$local_col = $row['local_column'];
		$foreign_col = $row['foreign_column'];
		$type = $row['constraint_type'];

		if (!isset($constraints[$name])) {
			$constraints[$name] = new Constraint($type, $name, $on_table, $to_table);
		}

		$constraints[$name]->addColumn(Pair { $local_col, $foreign_col });

	}

	vprint("Read ".$constraints->count()." constraint(s)");

	return Vector::fromItems($constraints->values());
}

function resolve_fk_constraints(Vector<Table> $tables, Vector<Constraint> $constraints) {
	vprint("Resolving constraints");

	foreach ($constraints as $constraint) {
		foreach ($tables as $table) {
			if ($table->name == $constraint->on_table) {
				if ($constraint->type == 'FOREIGN KEY') {
					$table->addHasOne($constraint->name, $constraint->to_table, $constraint->columns);
				} else if ($constraint->type == 'PRIMARY KEY') {
					$cols = Vector::fromItems($constraint->columns->map(function ($col) {
						return $col[0];
					}));
					$table->setPrimaryKey($cols);
				}
			}
			if ($table->name == $constraint->to_table) {
				$table->addHasMany($constraint->name, $constraint->on_table, $constraint->columns);
			}
		}
	}
}

class CodeFile {

	public $path;
	public $file = null;

	private $indent = 0;
	private $braces = Vector {};

	public function __construct(string $path) {
		$this->path = $path;

		$this->file = fopen($this->path, "w");
		if (!$this->file) {
			command_fail("Could not open file '$this->path' for writing");
		}
	}

	public function writeLine(string $line="", int $indent=null) {
		if ($this->file == null) command_fail("Code file closed!");
		$indent = str_repeat("\t", $indent === null ? $this->indent : (int)$indent);
		$line = sprintf("%s%s", $indent, $line);
		fwrite($this->file, rtrim($line)."\n");
	}

	public function startBlock(string $before="", string $brace_char='{') {
		$this->writeLine(trim($before." ".$brace_char));
		$this->braces->append(reverse_brace($brace_char));
		$this->indent++;
	}

	public function endBlock() {
		$this->indent--;
		$end = $this->braces->pop();
		if ($end != '') {
			$this->writeLine($end);
		}
	}

	public function writePHPPreamble(bool $dne=true) {
		if ($this->file == null) command_fail("Code file closed!");
		fwrite($this->file, "<?hh\n");

		if($dne) {
			$this->blockComment('DO NOT EDIT THIS FILE');
		}
	}

	public function blockComment(string $comment, bool $doc=false) {
		$lines = explode("\n", $comment);
		if (count($lines) > 0) {
			if ($doc) {
				$this->writeLine("/**");
			} else {
				$this->writeLine("/*");
			}
			foreach ($lines as $line) {
				$this->writeLine(" * $line");
			}
			$this->writeLine(" */");
		}
	}

	public function close() {
		fclose($this->file);
		$this->file = null;
	}
}

function generate_php(Vector<Table> $tables, TypeDict $dict, string $directory, string $ns=null) {
	$ns = $ns ? $ns : "";

	$types_file = new CodeFile($directory.'/types.php', "w");

	$types_file->writePHPPreamble(true);
	$types_file->writeLine();
	$types_file->writeLine("namespace $ns;");
	$types_file->writeLine();

	foreach ($tables as $table) {
		if ($table->columns->count() == 0) continue;

		vprint("Generating code for table \"$table->name\"");

		$tbl_data = new CodeFile($directory.'/'.$table->name.'.php');
		$tbl_data->writePHPPreamble(true);

		$tbl_data->writeLine();
		$tbl_data->writeLine("namespace $ns;");
		$tbl_data->writeLine();
		$tbl_data->writeLine('use beatbox\\orm\\ORM, beatbox\\orm\\Connection;');
		$tbl_data->writeLine('use HH\Traversable;');
		$tbl_data->writeLine();

		$tbl_data->startBlock("abstract class $table->name extends \\beatbox\\orm\\DataTable");

		$tbl_data->writeLine();
		$tbl_data->writeLine("// Original data");
		$tbl_data->writeLine("private \$orig = null;");

		$tbl_data->writeLine();
		$tbl_data->writeLine("// Map to track changes");
		$tbl_data->writeLine("private \$changed = \Map {};");


		$tbl_data->writeLine();
		$tbl_data->blockComment("Table Columns");
		$tbl_data->writeLine();

		foreach ($table->columns as $col) {
			$type = $col->getType($dict);
			if ($type->needsWrite()) {
				$type->writeTo($types_file);
			}

			$desc = $col->description;
			if ($col->primary) {
				if ($desc) {
					$desc .= "\n\n";
				} else {
					$desc = "Primary Key Column";
				}
			}

			if ($desc) {
				$tbl_data->blockComment($desc, true);
			}
			$tbl_data->writeLine("private \$_$col->name = null; // $type");
		}

		$tbl_data->writeLine();
		$tbl_data->blockComment("Constructor - Internal use only");
		$tbl_data->startBlock("public function __construct(\$row = null)");
		$tbl_data->writeLine("assert(is_null(\$row) || is_array(\$row) || \$row instanceof \ConstMapAccess);");
		$tbl_data->startBlock('if($row)', '');
		$tbl_data->writeLine("\$this->updateFromRow(\$row);");
		$tbl_data->endBlock();
		$tbl_data->endBlock();

		$tbl_data->writeLine();
		$tbl_data->blockComment("Accessor Methods");
		$tbl_data->writeLine();
		foreach ($table->columns as $col) {
			$type = $col->getType($dict);

			$type_arg = ltrim($type." ");
			$isDateTime = $type->type_cat == PGType::TCAT_DATETIME;
			$type_ret = strlen($type_arg) ? ": $type" : '';

			$tbl_data->startBlock("public function get$col->name()$type_ret");
			$tbl_data->writeLine("return \$this->_$col->name;");
			$tbl_data->endBlock();

			$def_val = $col->nullable ? ' = null' : '';

			if ($col->updatable) {
				if($isDateTime) {
					$tbl_data->startBlock("public function set$col->name(\$val)");
					if(!$col->nullable) {
						$tbl_data->writeLine('assert(!is_null($val));');
					}
					$tbl_data->startBlock('if(is_string($val))');
					$tbl_data->writeLine('$val = new \beatbox\orm\DateTimeType($val);');
					$tbl_data->endBlock();
					$tbl_data->startBlock('elseif(is_numeric($val))');
					$tbl_data->writeLine('$val = new \beatbox\orm\DateTimeType(\'@\' . $val);');
					$tbl_data->endBlock();
					$tbl_data->writeLine('assert($val instanceof \beatbox\orm\DateTimeType || $val === null);');
					$tbl_data->writeLine("\$this->changed['$col->name'] = ".
						"\$this->orig == null || (\$this->orig['$col->name']->cmp(\$val) != 0);");
					$tbl_data->writeLine("\$this->_$col->name = \$val;");
					$tbl_data->writeLine('return $this;');
				} elseif ($type->isSimple()) {
					$tbl_data->startBlock("public function set$col->name($type_arg\$val$def_val)");
					$tbl_data->writeLine('assert(func_num_args() > 0);');
					$tbl_data->writeLine("\$this->changed['$col->name'] = ".
						"\$this->orig == null || (\$this->orig['$col->name'] !== \$val);");
					$tbl_data->writeLine("\$this->_$col->name = \$val;");
					$tbl_data->writeLine('return $this;');
				} else if ($type->type_cat == PGType::TCAT_ARRAY) {
					$tbl_data->startBlock("public function set$col->name(Traversable \$val$def_val)");
					$tbl_data->writeLine('assert(func_num_args() > 0);');
					$tbl_data->writeLine("\$this->changed['$col->name'] = true;");
					$tbl_data->writeLine("\$this->_$col->name = \Vector {};");
					$tbl_data->writeLine("\$this->_$col->name->addAll(\$val);");
					$tbl_data->writeLine('return $this;');
					$tbl_data->endBlock();
					$tbl_data->startBlock("public function append$col->name(Traversable \$val$def_val)");
					$tbl_data->writeLine('assert(func_num_args() > 0);');
					$tbl_data->writeLine("\$this->changed['$col->name'] = true;");
					$tbl_data->writeLine("\$this->_$col->name->addAll(\$val);");
					$tbl_data->writeLine('return $this;');
				} else {
					$tbl_data->startBlock("public function set$col->name($type_arg\$val$def_val)");
					$tbl_data->writeLine('assert(func_num_args() > 0);');
					$tbl_data->writeLine("\$this->changed['$col->name'] = true;");
					$tbl_data->writeLine("\$this->_$col->name = \$val;");
					$tbl_data->writeLine('return $this;');
				}
				$tbl_data->endBlock();
			}

			$tbl_data->writeLine();
		}

		foreach ($table->hasOnes() as $has_one) {
			$relname = $has_one[0];
			$table_name = $has_one[1];
			$columns = $has_one[2];

			$tbl_data->startBlock("public function getOne{$relname}(\$klass): ORM ");

			$tbl_data->writeLine("\$query = new ORM(\$klass);");
			foreach ($columns as $i => $col_pair) {
				list($local,$foreign) = $col_pair;
				$tbl_data->writeLine("\$query = \$query->filter('$foreign', \$this->_$local);");
			}
			$tbl_data->writeLine("return \$query->limit(1);");

			$tbl_data->endBlock();
		}

		foreach ($table->hasManys() as $has_many) {
			$relname = $has_many[0];
			$table_name = $has_many[1];
			$columns = $has_many[2];

			$tbl_data->startBlock("public function getMany{$relname}(\$klass): ORM");

			$tbl_data->writeLine("\$query = new ORM(\$klass);");
			foreach ($columns as $i => $col_pair) {
				list($foreign,$local) = $col_pair;
				$tbl_data->writeLine("\$query = \$query->filter('$foreign', \$this->_$local);");
			}
			$tbl_data->writeLine("return \$query;");

			$tbl_data->endBlock();
		}

		$tbl_data->writeLine();
		$tbl_data->blockComment("Abstract function implementations");
		$tbl_data->writeLine();

		$tbl_data->startBlock("protected final function updateFromRow(\$row)");
		$tbl_data->writeLine('$this->orig = \Map {};');
		$tbl_data->writeLine('$this->changed = \Map {};');

		foreach ($table->columns as $col) {
			$type = $col->getType($dict);
			$tbl_data->startBlock("if (\$row['$col->name'] !== null)");
			$type->convertCode($tbl_data, "\$this->_$col->name", "\$row['$col->name']");
			$tbl_data->endBlock();
			$tbl_data->writeLine("\$this->orig['$col->name'] = \$this->_$col->name;");
		}

		$tbl_data->endBlock();

		$tbl_data->startBlock("public final static function getTableName(): \string");
		$tbl_data->writeLine("return '$table->name';");
		$tbl_data->endBlock();


		$tbl_data->startBlock("public final function getUpdatedColumns(): \Map");

		$tbl_data->writeLine('$changed = \Map {};');
		foreach ($table->columns as $col) {
			$tbl_data->startBlock("if (\$this->changed->get('$col->name'))", '');
			$tbl_data->writeLine("\$changed['$col->name'] = \$this->_$col->name;");
			$tbl_data->endBlock();
		}
		$tbl_data->writeLine('return $changed;');

		$tbl_data->endBlock();

		$tbl_data->writeLine();

		$tbl_data->startBlock("public final function toMap(): \Map");

		$tbl_data->writeLine('$map = \Map {};');
		foreach ($table->columns as $col) {
			$tbl_data->writeLine("\$map['$col->name'] = \$this->_$col->name;");
		}
		$tbl_data->writeLine('return $map;');

		$tbl_data->endBlock();

		$tbl_data->writeLine();

		$tbl_data->startBlock('public final function toRow(): \string');
		$tbl_data->writeLine('$con = Connection::get();');
		$tbl_data->writeLine('$values = [];');
		foreach($table->columns as $col) {
			$tbl_data->writeLine("\$values[] = \$this->_$col->name;");
		}
		$tbl_data->writeLine('$values = array_map([$con, \'escapeValue\'], $values);');
		$tbl_data->writeLine('return \'ROW(\' . implode(\',\', $values) . \')\';');
		$tbl_data->endBlock();
		$tbl_data->writeLine();

		$tbl_data->startBlock("public final static function getColumnNames(): \Set<string>");

		$tbl_data->startBlock('return \Set {', '');
		foreach ($table->columns as $col) {
			$tbl_data->writeLine("'$col->name',");
		}
		$tbl_data->endBlock();
		$tbl_data->writeLine("};");

		$tbl_data->endBlock();

		$tbl_data->writeLine();

		if($table->primaryKeys()->count()) {
			$final = ' final';
		} else {
			$final = '';
		}
		$tbl_data->startBlock("public$final static function getPrimaryKeys(): \Set<string>");

		$tbl_data->writeLine('$keys = \Set {};');
		foreach ($table->primaryKeys() as $key) {
			$tbl_data->writeLine("\$keys->add(\"$key->name\");");
		}
		$tbl_data->writeLine('return $keys;');

		$tbl_data->endBlock();

		$tbl_data->writeLine();

		$tbl_data->startBlock("public final function isNew(): bool");
		$tbl_data->writeLine('return $this->orig == null;');
		$tbl_data->endBlock();
		$tbl_data->endBlock();
		$tbl_data->close();

	}
}

function reverse_brace(string $brace): string {
	switch($brace) {
	case '{':
		return '}';
	case '}':
		return '{';
	case '[':
		return ']';
	case ']':
		return '[';
	case '(':
		return ')';
	case ')':
		return '(';
	case '': // I know it's not a brace, but it helps to lay out code nicely.
		return '';
	default:
		command_fail("Invalid brace character '$brace'");
	}
}
