<?hh

/**
 * HipHop made `array instanceof Traversable` return false at some point,
 * but HH\Traversable is still fine. This makes sure things still work on
 * older versions of HipHop.
 */

namespace HH {

interface Traversable extends \Traversable { }

}

class array implements Traversable;
