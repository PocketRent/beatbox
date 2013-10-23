#!/usr/bin/hhvm -v Eval.EnableHipHopSyntax=true
<?hh

chdir(__DIR__);
$base = dirname(__DIR__);

set_include_path(__DIR__. ':' . $base . '/pear');
require 'phpunit.php';
