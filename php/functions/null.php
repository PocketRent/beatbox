<?hh

/**
 * A null was found in an unexpected place. Indicates programmer error, not bad
 * input or anything like that.
 */
class UnexpectedNullException extends Exception {}

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

	throw new UnexpectedNullException('Got unexpected null');
}
