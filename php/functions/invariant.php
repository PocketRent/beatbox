<?hh // strict

class InvariantViolationException extends Exception {}

class ExpectedCallableExpection extends InvariantViolationException {}

/**
 * A null was found in an unexpected place. Indicates programmer error, not bad
 * input or anything like that.
 */
class UnexpectedNullException extends InvariantViolationException {}
/**
 * Use this function when you are sure a value can never be null and want to
 * express that fact to the type system. If the value happens to actually be
 * null at runtime, throws an exception indicating programmer error -- you
 * promised this would never be null but it was after all!
 */
function nullthrows<T>(?T $x): T {
	if ($x !== null) {
		return $x;
	}

	$caller_info = hphp_debug_caller_info();

	$file = (string)$caller_info['file'];
	$line = (int)$caller_info['line'];
	throw new UnexpectedNullException("Got unexpected null at $file:$line");
}

function cast_callable<Tf>(mixed $val) : Tf {
	// UNSAFE
	if (is_callable($val))
		return $val;
	else
		throw new ExpectedCallableExpection('Expected callable');
}
