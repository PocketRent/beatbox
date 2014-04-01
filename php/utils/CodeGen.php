<?hh // strict

namespace beatbox\utils;

use \Stringish;

newtype CodeVisibility = int;

/**
 * Code generation utility for generating Hack code more easily than trying to do it by hand.
 */

class CodeFile implements Writeable {
	private string $filename = '';

	private string $namespace = '';
	private Vector<string> $uses = Vector {};

	private Vector<CodeItem> $items = Vector {};

	private ?string $prelude;
	private bool $isStrict = false;

	public function setNamespace(string $ns) : void {
		$this->namespace = $ns;
	}

	public function addUse(string $use) : void {
		$this->uses->add($use);
	}

	public function setPrelude(string $p) : void {
		$this->prelude = $p;
	}

	public function setFilename(string $name) : void {
		$this->filename = $name;
	}

	public function setStrict(bool $val = true) : void {
		$this->isStrict = $val;
	}

	public function func(string $name,
		Vector<CodeArgument> $args,
		string $returnType,
		?Vector<string> $typeParams=null) : CodeFunction {

		$fn = new CodeFunction($name, $args, $returnType, $typeParams);
		$this->items->add($fn);
		return $fn;
	}

	public function cls(string $name) : CodeClass {
		$cls = new CodeClass($name);
		$this->items->add($cls);
		return $cls;
	}

	public function writeToFile(string $filename) : void {
		$this->setFilename($filename);
		$handle = fopen($filename, 'w+');
		$writer = FileWriter::fromHandle($handle);
		$this->write($writer);
		fflush($handle);
		fclose($handle);
	}

	public function write(FileWriter $writer) : void {
		if ($this->isStrict) {
			$writer->str('<?hh // strict');
		} else {
			$writer->str('<?hh');
		}
		$writer->ensureNewline();
		$writer->newline();

		if ($this->namespace != '') {
			$writer->str('namespace %s;', $this->namespace);
			$writer->ensureNewline();
		}

		$this->writePrelude($writer);

		foreach ($this->uses as $use) {
			$writer->str('use %s;', $use);
			$writer->ensureNewline();
		}

		foreach ($this->items as $item) {
			$item->writeComment($writer);
			$item->write($writer);
			$writer->ensureNewline();
			$writer->newline();
		}
	}

	private function writePrelude(FileWriter $writer) : void {
		if ($this->prelude !== null) {
			if ($this->prelude == '') return;
			$prelude = $this->processPrelude();
			$lines = explode("\n", $prelude);
			$writer->str('/**');
			$writer->ensureNewline();
			foreach ($lines as $l) {
				if (trim($l) == '')
					$writer->str(' *');
				else
					$writer->str(' * %s', $l);
				$writer->ensureNewline();
			}
			$writer->str(' */');
			$writer->ensureNewline();
			$writer->newline();
		}
	}

	private function processPrelude() : string {
		$prelude = nullthrows($this->prelude);

		$prelude = preg_replace('#([^%]?)%d#', "\${1}".date('Y-m-d'), $prelude) ?: $prelude;
		$prelude = preg_replace('#([^%]?)%f#', "\${1}$this->filename", $prelude) ?: $prelude;

		return $prelude;
	}
}

const CodeVisibility VIS_PUBLIC    = 1;
const CodeVisibility VIS_PROTECTED = 2;
const CodeVisibility VIS_PRIVATE   = 3;

abstract class CodeItem implements Writeable {
	protected string $name;
	protected ?string $comment;

	public function __construct(string $name) {
		$this->name = $name;
	}

	public function setComment(string $comment) : void {
		$this->comment = $comment;
	}

	public function writeComment(FileWriter $writer) : void {
		if ($this->comment !== null) {
			if ($this->comment == '') return;
			$lines = explode("\n", $this->comment);
			$writer->str('/**');
			$writer->ensureNewline();
			foreach ($lines as $l) {
				if (trim($l) == '')
					$writer->str(' *');
				else
					$writer->str(' * %s', $l);
				$writer->ensureNewline();
			}
			$writer->str(' */');
			$writer->ensureNewline();
		}
	}
}

class CodeFunction extends CodeItem {
	private Vector<CodeArgument> $arguments;
	private ?Vector<string> $typeParams;
	private string $returnType;

	private CodeBlock $block;

	public function __construct(string $name,
		Vector<CodeArgument> $args,
		string $returnType,
		?Vector<string> $typeParams=null) {

		parent::__construct($name);

		$this->arguments = $args;
		$this->returnType = $returnType;
		$this->typeParams = $typeParams;

		$this->block = new CodeBlock();
	}

	public function block() : CodeBlock {
		return $this->block;
	}

	public function write(FileWriter $writer) : void {
		$writer->str("function %s", $this->name);
		if ($this->typeParams) {
			$writer->strList('<>', $this->typeParams);
		}

		$writer->writeList('()', $this->arguments);
		$writer->str(' : %s ', $this->returnType);
		$this->block->write($writer);
	}
}

class CodeClass extends CodeItem {
	private Vector<CodeField> $fields = Vector {};
	private Vector<CodeMethod> $methods = Vector {};

	private ?string $extends;
	private Vector<string> $implements = Vector {};

	private bool $abstract = false;
	private bool $final = false;

	public function extends_(string $baseClass) : void {
		$this->extends = $baseClass;
	}

	public function implements_(Vector<string> $interfaces) : void {
		$this->implements = $interfaces;
	}

	public function field(string $type, string $name) : CodeField {
		$field = new CodeField($type, $name);
		$this->fields->add($field);
		return $field;
	}

	public function method(string $name,
		Vector<CodeArgument> $args,
		string $returnType,
		?Vector<string> $typeParams=null) : CodeMethod {

		$fn = new CodeMethod($name, $args, $returnType, $typeParams);
		$this->methods->add($fn);
		return $fn;
	}

	public function write(FileWriter $writer) : void {
		if ($this->abstract)
			$writer->str('abstract ');
		else if ($this->final)
			$writer->str('final ');

		$writer->str('class %s', $this->name);
		if ($this->extends) {
			$writer->str(' extends %s', $this->extends);
		}
		if ($this->implements->count() > 0) {
			$writer->str(' implements ');
			$writer->strList('', $this->implements);
		}

		$writer->str(' {');
		$writer->startBlock();
		foreach ($this->fields as $f) {
			$f->writeComment($writer);
			$f->write($writer);
			$writer->ensureNewline();
		}
		foreach ($this->methods as $m) {
			$m->writeComment($writer);
			$m->write($writer);
			$writer->ensureNewline();
		}
		$writer->newline();
		$writer->endBlock();
		$writer->str('}');
	}

}

class CodeMethod extends CodeFunction {

	private CodeVisibility $visibility = VIS_PUBLIC;
	private bool $static = false;

	private bool $abstract = false;
	private bool $final = false;

	public function setStatic(bool $static = true) : void {
		$this->static = $static;
	}

	public function setVisibility(CodeVisibility $type) : void {
		$this->visibility = $type;
	}

	public function write(FileWriter $writer) : void {
		$writer->writeVis($this->visibility);

		if ($this->abstract)
			$writer->str('abstract ');
		else if ($this->final)
			$writer->str('final ');

		if ($this->static)
			$writer->str('static ');

		parent::write($writer);
	}
}

abstract class CodeStatement implements Writeable { }

class CodeBlock extends CodeStatement {
	private Vector<CodeStatement> $statements = Vector {};

	public function assign(string $variable, string $value) : void {
		$this->statements->add(new AssignmentStatement($variable, $value));
	}

	public function ret(?string $expr = null) : void {
		$this->statements->add(new ReturnStatement($expr));
	}

	public function call(string $func, Vector<string> $args) : void {
		$this->statements->add(new CallStatement($func, $args));
	}

	public function methodCall(string $obj, string $func, Vector<string> $args) : void {
		$func = sprintf('%s->%s', $obj, $func);
		$this->call($func, $args);
	}

	public function if_(string $condition) : IfStatement {
		$if = new IfStatement($condition);
		$this->statements->add($if);
		return $if;
	}

	public function write(FileWriter $writer) : void {
		if ($this->statements->count() == 0) {
			$writer->str('{}');
		} else {
			$writer->str('{');
			$writer->startBlock();
			foreach ($this->statements as $statement) {
				$statement->write($writer);
				$writer->ensureNewline();
			}
			$writer->endBlock();
			$writer->str('}');
		}
	}
}

class AssignmentStatement extends CodeStatement {
	private string $variable;
	private string $value;

	public function __construct(string $variable, string $value) {
		$this->variable = $variable;
		$this->value = $value;
	}

	public function write(FileWriter $writer) : void {
		$writer->str('$%s = %s;', $this->variable, $this->value);
		$writer->ensureNewline();
	}
}

class ReturnStatement extends CodeStatement {
	private ?string $expression;

	public function __construct(?string $expression) {
		$this->expression = $expression;
	}

	public function write(FileWriter $writer) : void {
		if ($this->expression !== null) {
			$writer->str('return %s;', $this->expression);
		} else {
			$writer->str('return;');
		}
		$writer->ensureNewline();
	}
}

class CallStatement extends CodeStatement {
	private string $func;
	private Vector<string> $args;

	public function __construct(string $func, Vector<string> $args) {
		$this->func = $func;
		$this->args = $args;
	}

	public function write(FileWriter $writer) : void {
		$writer->str('%s', $this->func);
		$writer->strList('()', $this->args);
		$writer->str(';');
	}
}

class IfStatement extends CodeBlock {
	private string $condition;
	private ?CodeBlock $else;

	public function __construct(string $condition) {
		$this->condition = $condition;
	}

	public function else_() : CodeBlock {
		$this->else = new CodeBlock();
		return $this->else;
	}

	public function else_if(string $condition) : IfStatement {
		$this->else = new IfStatement($condition);
		return $this->else;
	}

	public function write(FileWriter $writer) : void {
		$writer->str('if (%s) ', $this->condition);
		parent::write($writer);
		$else = $this->else;
		if ($else) {
			$writer->str(' else ');
			$else->write($writer);
		}
	}
}

class CodeArgument implements Writeable {
	private string $type;
	private string $name;
	private ?string $default;

	public function __construct(string $name, string $type, ?string $default=null) {
		$this->name = $name;
		$this->type = $type;
		$this->default = $default;
	}

	public function write(FileWriter $writer) : void {
		$writer->str('%s $%s', $this->type, $this->name);
		if ($this->default !== null) {
			$writer->str(' = %s', $this->default);
		}
	}
}

class CodeField implements Writeable {
	private CodeVisibility $visibility = VIS_PRIVATE;
	private string $type;
	private string $name;
	private ?string $default;

	private ?string $comment;

	public function __construct(string $type, string $name) {
		$this->type = $type;
		$this->name = $name;
	}

	public function setVisibility(CodeVisibility $vis) : void {
		$this->visibility = $vis;
	}

	public function setDefault(string $default) : void {
		$this->default = $default;
	}

	public function write(FileWriter $writer) : void {
		$writer->writeVis($this->visibility);
		$writer->str('%s $%s', $this->type, $this->name);
		if ($this->default !== null) {
			$writer->str(' = %s', $this->default);
		}
		$writer->str(';');
	}

	public function writeComment(FileWriter $writer) : void {
		if ($this->comment !== null) {
			if ($this->comment == '') return;
			$lines = explode("\n", $this->comment);
			$writer->str('/**');
			$writer->ensureNewline();
			foreach ($lines as $l) {
				if (trim($l) == '')
					$writer->str(' *');
				else
					$writer->str(' * %s', $l);
				$writer->ensureNewline();
			}
			$writer->str(' */');
			$writer->ensureNewline();
		}
	}
}

interface Writeable {
	public function write(FileWriter $writer) : void;
}

class FileWriter {
	const string INDENT_CHAR = "\t";
	const int INDENT_MULTIPLIER = 1;

	private ?resource $fileHandle;
	private bool $hasNewline = true;
	private int $indent = 0;

	public static function fromHandle(resource $handle) : FileWriter {
		$writer = new FileWriter();
		$writer->fileHandle = $handle;
		return $writer;
	}

	public function str(string $s, ...) : void {
		$args = func_get_args();
		array_shift($args);

		$str = vsprintf($s, $args);

		$this->write($str);
	}

	public function strList<T as Stringish>(string $delims, Traversable<T> $items) : void {
		if (strlen($delims) == 0) {
			$open = $close = '';
		} else if (strlen($delims) == 1) {
			$open = $close = $delims;
		} else {
			$open = $delims[0];
			$close = $delims[1];
		}
		$this->write($open);

		$comma = false;
		foreach ($items as $item) {
			if ($comma)
				$this->write(", ");
			$comma = true;
			$this->write((string)$item);
		}
		$this->write($close);
	}

	public function writeList<T as Writeable>(string $delims, Traversable<T> $items) : void {
		if (strlen($delims) == 0) {
			$open = $close = '';
		} else if (strlen($delims) == 1) {
			$open = $close = $delims;
		} else {
			$open = $delims[0];
			$close = $delims[1];
		}
		$this->write($open);

		$comma = false;
		foreach ($items as $item) {
			if ($comma)
				$this->write(", ");
			$item->write($this);
			$comma = true;
		}
		$this->write($close);
	}

	public function startBlock() : void {
		$this->ensureNewline();
		$this->indent++;
	}

	public function endBlock() : void {
		$this->indent--;
		$this->ensureNewline();
	}

	public function ensureNewline() : void {
		if (!$this->hasNewline) {
			$this->newline();
		}
	}

	public function newline() : void {
		fwrite($this->handle(), "\n");
		$this->hasNewline = true;
	}

	public function writeVis(CodeVisibility $vis) : void {
		switch ($vis) {
		case VIS_PUBLIC:
			$this->write('public ');
			break;
		case VIS_PROTECTED:
			$this->write('protected ');
			break;
		case VIS_PRIVATE:
			$this->write('private ');
			break;
		}
	}

	private function write(string $s) : void {
		$len = strlen($s);
		if ($len == 0) return;
		fprintf($this->handle(), "%s%s", $this->indent(), $s);
		$this->hasNewline = false;
	}

	private function indent() : string {
		if ($this->indent == 0) return "";
		if (!$this->hasNewline) return "";
		return str_repeat(self::INDENT_CHAR, $this->indent*self::INDENT_MULTIPLIER);
	}

	private function handle() : resource {
		return nullthrows($this->fileHandle);
	}
}
