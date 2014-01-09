<?hh

require_once __DIR__.'/../../php/functions/invariant.php';
require_once __DIR__.'/../../php/functions/misc.php';
require_once 'create.php';
require_once 'generate.php';

// Parses common args and strips them out of the argument list,
// returns a map with the database info in it.
function parse_common_args(Vector<string> &$args): Map<string,mixed> {
	$GLOBALS['verbose'] = false;

	$rest_of = Vector {};

	$iter = $args->getIterator();
	$iter->rewind();

	$getNext = function(string $opt) : string use ($iter) {
		$iter->next();
		if (!$iter->valid()) command_fail("Expected value for $opt");
		return $iter->current();
	};

	$info = Map { };

	$conf_dir = getcwd().'/conf';

	while ($iter->valid()) {
		$arg = $iter->current();
		switch ($arg) {
		case "-C":
		case "--conf-dir":
			$conf_dir = $getNext("conf-dir");
			break;
		case "-d":
		case "--database":
			$info['database'] = $getNext("database");
			break;
		case "-h":
		case "--host":
			$info['host'] = $getNext("host");
			break;
		case "-u":
		case "--user":
			$info['user'] = $getNext("user");
			break;
		case "-p":
		case "--pass":
			$info['pass'] = $getNext("pass");
			break;
		case "-V":
		case "--verbose":
			$GLOBALS['verbose'] = true;
			break;
		default:
			$rest_of->add($arg);
			break;
		}
		$iter->next();
	}

	unset($iter);
	$args = $rest_of;
	unset($rest_of);

	// Check to see if we need to include 'db.php'
	if (!($info->get('database') && $info->get('host')
			&& $info->get('user') && $info->get('pass'))) {
		$db_conf = $conf_dir . '/db.php';
		if (!file_exists($db_conf)) {
			command_fail("Cannot find database configuration file to include");
		}
		require_once $db_conf;

		if (!$info->get('database')) {
			if (!defined('DATABASE_NAME')) command_fail("No database name value available");
			$info['database'] = DATABASE_NAME;
		}

		if (!$info->get('host')) {
			if (!defined('DATABASE_HOST')) command_fail("No database host value available");
			$info['host'] = DATABASE_HOST;
		}

		if (!$info->get('user')) {
			if (!defined('DATABASE_USER')) command_fail("No database user value available");
			$info['user'] = DATABASE_USER;
		}

		if (!$info->get('pass')) {
			if (!defined('DATABASE_PASS')) command_fail("No database password value available");
			$info['pass'] = DATABASE_PASS;
		}
	}

	return $info;
}

function do_connect(Map<string,mixed> $info) : resource {

	$conn_string = '';
	if ($info['host']) $conn_string .= ' host=\''.(string)$info['host'].'\'';
	if ($info['user']) $conn_string .= ' user=\''.(string)$info['user'].'\'';
	if ($info['pass']) $conn_string .= ' password=\''.(string)$info['pass'].'\'';

	if (isset($info['create_db']) && $info['create_db']) {
		($conn = pg_connect($conn_string . ' dbname=postgres')) ||
					command_fail("Unable to connect to postgres");
		@pg_query($conn, "CREATE DATABASE ".pg_escape_identifier($conn, $info['database']));
		pg_close($conn);
	}

	($conn = pg_connect($conn_string . "dbname='".$info['database']."'")) ||
				command_fail("Unable to connect to postgres");

	vprint("Connected to database ".(string)$info['database']." at ".(string)$info['host']);

	return $conn;
}

function format_desc(string $desc): string {
	$width = 50;
	$wrapped = wordwrap($desc, $width);
	$lines = explode("\n", $desc);
	for ($i=1; $i < count($lines); $i++) {
		$lines[$i] = sprintf("%79s", $lines[$i]);
	}
	return implode("\n", $lines);
}

class CommandException extends Exception {
	public function __construct(string $msg, int $code = 0) {
		parent::__construct($msg, $code);
	}
}

function command_fail(string $msg, int $code=1) : void {
	throw new CommandException($msg, $code);
}

function vprint(string $msg) : void {
	if ($GLOBALS['verbose']) {
		echo $msg, "\n";
	}
}
