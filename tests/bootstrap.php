<?hh

// Test defines
define('RUNNING_TEST', true); // For code that needs to adjust for tests

// Asset path should be a temp directory
$assets = "/assets".(substr(md5(rand()),0,8));
$dir = getenv("TMPDIR");
if (!$dir) $dir = getenv("TMP");
if (!$dir) $dir = '/tmp';
define('ASSET_PATH', $dir.$assets);

// Load the stuff
require __DIR__ . '/../php/init.php';

// Connect to the database

$connectString = 'dbname=\'postgres\'';
if (DATABASE_HOST) $connectString .= ' host=\''.DATABASE_HOST.'\'';
if (DATABASE_USER) $connectString .= ' user=\''.DATABASE_USER.'\'';
if (DATABASE_PASS) $connectString .= ' password=\''.DATABASE_PASS.'\'';

$con = @pg_connect($connectString) or die('Unable to connect to postgres');

$dbname = 'PR_test_' . str_replace('.', '_', microtime(true));

// Create the database
pg_query($con, 'CREATE DATABASE "' . $dbname . '"') or die('Unable to create database');
pg_close($con);

// Build the schema by running the command
$args = ['create', '-d', $dbname, '-C', __DIR__ . '/../conf/', '--ignore-buildfiles', __DIR__ . '/../db/'];
$cmd = escapeshellcmd(__DIR__ . '/../build/database');
$args = array_map('escapeshellarg', $args);
$cmd = $cmd . ' ' . implode(' ', $args);

passthru($cmd);

// Create a new connection to set the global object
$con = new beatbox\orm\Connection(['dbname' => $dbname]);

// Shutdown handler
register_shutdown_function(function() use($connectString, $dbname, $con) {
	$con->close();
	$con = pg_connect($connectString);
	pg_query($con, 'DROP DATABASE "' . $dbname . '"');
	pg_close($con);

	// Delete temporary assets folder
	if (file_exists(ASSET_PATH))
		exec('/bin/rm -rf '.ASSET_PATH);
});
