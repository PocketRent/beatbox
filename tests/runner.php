#!/usr/bin/env hhvm
<?hh

chdir(__DIR__);
$base = dirname(__DIR__);

set_include_path(__DIR__. ':' . $base . '/pear');

define('PHPUnit_MAIN_METHOD', 'PHPUnit_TextUI_Command::main');

require 'PHPUnit' . DIRECTORY_SEPARATOR . 'Autoload.php';

exit(PHPUnit_TextUI_Command::main());
