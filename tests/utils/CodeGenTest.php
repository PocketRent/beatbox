<?hh

namespace beatbox\test;

use beatbox;
use beatbox\utils;

class CodeGenTest extends beatbox\Test {
	/**
	 * @group sanity
	 */
	public function testEmptyFile() {
		$file = new utils\CodeFile();

		$expected = <<<EOF
<?hh


EOF;
		$actual = $this->write($file);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @group sanity
	 */
	public function testEmptyStrictFile() {
		$file = new utils\CodeFile();
		$file->setStrict();

		$expected = <<<EOF
<?hh // strict


EOF;
		$actual = $this->write($file);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @group sanity
	 */
	public function testSimpleFunction() {
		$func = new utils\CodeFunction('testFunc', Vector {}, 'void');

		$expected = <<<'EOF'
function testFunc() : void {}
EOF;
		$actual = $this->write($func);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @group fast
	 */
	public function testFunctionWithArgs() {
		$args = Vector {
			new utils\CodeArgument('foo', 'string'),
			new utils\CodeArgument('bar', 'Obj'),
		};
		$func = new utils\CodeFunction('testFuncArgs', $args, 'void');

		$expected = <<<'EOF'
function testFuncArgs(string $foo, Obj $bar) : void {}
EOF;
		$actual = $this->write($func);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @group fast
	 */
	public function testFunctionWithArgsAndTypeParams() {
		$args = Vector {
			new utils\CodeArgument('foo', 'string'),
			new utils\CodeArgument('bar', 'T'),
		};
		$params = Vector { 'T' };
		$func = new utils\CodeFunction('testFuncArgsWParams', $args, 'void', $params);

		$expected = <<<'EOF'
function testFuncArgsWParams<T>(string $foo, T $bar) : void {}
EOF;
		$actual = $this->write($func);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @group fast
	 */
	public function testFunctionBody() {
		$args = Vector { };
		$func = new utils\CodeFunction('testFunc', $args, 'string');

		$block = $func->block();
		$block->ret('"foo"');

		$expected = <<<'EOF'
function testFunc() : string {
	return "foo";
}
EOF;
		$actual = $this->write($func);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @group fast
	 */
	public function testFunctionBody2() {
		$args = Vector { };
		$func = new utils\CodeFunction('testFunc', $args, 'string');

		$block = $func->block();
		$block->assign('a', 'foo()');
		$block->ret('$a');

		$expected = <<<'EOF'
function testFunc() : string {
	$a = foo();
	return $a;
}
EOF;
		$actual = $this->write($func);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @group fast
	 */
	public function testIfStatement() {
		$args = Vector { };
		$block= new utils\CodeBlock();

		$if = $block->if_('$foo');
		$if->call('var_dump', Vector {'$foo'});

		$expected = <<<'EOF'
{
	if ($foo) {
		var_dump($foo);
	}
}
EOF;
		$actual = $this->write($block);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @group fast
	 */
	public function testIfElse() {
		$args = Vector { };
		$block= new utils\CodeBlock();

		$if = $block->if_('$foo');
		$if->call('var_dump', Vector {'$foo'});
		$else = $if->else_();
		$else->call('exit', Vector {});

		$expected = <<<'EOF'
{
	if ($foo) {
		var_dump($foo);
	} else {
		exit();
	}
}
EOF;
		$actual = $this->write($block);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @group fast
	 */
	public function testIfElseIf() {
		$args = Vector { };
		$block= new utils\CodeBlock();

		$if = $block->if_('$foo');
		$if->call('var_dump', Vector {'$foo'});
		$elseif = $if->else_if('$bar');
		$elseif->call('exit', Vector {});

		$expected = <<<'EOF'
{
	if ($foo) {
		var_dump($foo);
	} else if ($bar) {
		exit();
	}
}
EOF;
		$actual = $this->write($block);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @group sanity
	 */
	public function testEmptyClass() {
		$cls = new utils\CodeClass('TestClass');

		$expected = <<<'EOF'
class TestClass {

}
EOF;
		$actual = $this->write($cls);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @group fast
	 */
	public function testClassExtends() {
		$cls = new utils\CodeClass('TestClass');
		$cls->extends_('TestBase');

		$expected = <<<'EOF'
class TestClass extends TestBase {

}
EOF;
		$actual = $this->write($cls);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @group fast
	 */
	public function testClassImplements() {
		$cls = new utils\CodeClass('TestClass');
		$cls->implements_(Vector {'ITest1, ITest2'});

		$expected = <<<'EOF'
class TestClass implements ITest1, ITest2 {

}
EOF;
		$actual = $this->write($cls);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @group fast
	 */
	public function testClassExtendsImplements() {
		$cls = new utils\CodeClass('TestClass');
		$cls->extends_('TestBase');
		$cls->implements_(Vector {'ITest1, ITest2'});

		$expected = <<<'EOF'
class TestClass extends TestBase implements ITest1, ITest2 {

}
EOF;
		$actual = $this->write($cls);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @group fast
	 */
	public function testClassFields() {
		$cls = new utils\CodeClass('TestClass');
		$f1 = $cls->field('string', 'f1');
		$f2 = $cls->field('string', 'f2');
		$f3 = $cls->field('string', 'f3');

		$f1->setDefault('"foo"');

		$f2->setVisibility(utils\VIS_PROTECTED);
		$f3->setVisibility(utils\VIS_PUBLIC);

		$expected = <<<'EOF'
class TestClass {
	private string $f1 = "foo";
	protected string $f2;
	public string $f3;

}
EOF;
		$actual = $this->write($cls);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @group fast
	 */
	public function testClassMethods() {
		$cls = new utils\CodeClass('TestClass');
		$f1 = $cls->method('m1', Vector {}, 'void');
		$f2 = $cls->method('m2', Vector {}, 'void');
		$f3 = $cls->method('m3', Vector {}, 'void');

		$f2->setVisibility(utils\VIS_PROTECTED);
		$f3->setVisibility(utils\VIS_PRIVATE);

		$expected = <<<'EOF'
class TestClass {
	public function m1() : void {}
	protected function m2() : void {}
	private function m3() : void {}

}
EOF;
		$actual = $this->write($cls);

		$this->assertEquals($expected, $actual);
	}

	private function write(utils\Writeable $x) : string {
		$handle = fopen("php://memory", "w+");
		$writer = utils\FileWriter::fromHandle($handle);
		$x->write($writer);

		fseek($handle, 0);

		$contents = '';
		while(!feof($handle)) {
			$contents .= fread($handle, 8192);
		}
		fclose($handle);

		return $contents;
	}
}
