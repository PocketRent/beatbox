<?hh // decl
/*
  +----------------------------------------------------------------------+
  | XHP                                                                  |
  +----------------------------------------------------------------------+
  | Copyright (c) 2009 - 2013 Facebook, Inc. (http://www.facebook.com)   |
  +----------------------------------------------------------------------+
  | This source file is subject to version 3.01 of the PHP license,      |
  | that is bundled with this package in the file LICENSE.PHP, and is    |
  | available through the world-wide-web at the following url:           |
  | http://www.php.net/license/3_01.txt                                  |
  | If you did not receive a copy of the PHP license and are unable to   |
  | obtain it through the world-wide-web, please send a note to          |
  | license@php.net so we can mail you a copy immediately.               |
  +----------------------------------------------------------------------+
*/

abstract class :x:base {
  abstract public function __construct($attributes, $children);
  abstract public function appendChild($child);
  abstract public function prependChild($child);
  abstract public function replaceChildren();
  abstract protected function getChildren($selector = null);
  abstract protected function getFirstChild($selector = null);
  abstract protected function getLastChild($selector = null);
  abstract public function getAttribute($attr);
  abstract public function getAttributes();
  abstract public function setAttribute($attr, $val);
  abstract public function setAttributes(array $attrs);
  abstract public function isAttributeSet($attr);
  abstract public function removeAttribute($attr);
  abstract public function categoryOf($cat);
  abstract public function __toString();
  abstract protected function &__xhpCategoryDeclaration();
  abstract protected function &__xhpChildrenDeclaration();
  protected static function &__xhpAttributeDeclaration() {}

  /**
   * Enabling validation will give you stricter documents; you won't be able to
   * do many things that violate the XHTML 1.0 Strict spec. It is recommend that
   * you leave this on because otherwise things like the `children` keyword will
   * do nothing. This validation comes at some CPU cost, however, so if you are
   * running a high-traffic site you will probably want to disable this in
   * production. You should still leave it on while developing new features,
   * though.
   */
  public static $ENABLE_VALIDATION = true;

  final protected static function renderChild($child) {
    if ($child instanceof :x:base) {
      return $child->__toString();
    } else if ($child instanceof HTML) {
      return $child->render();
    } else if (is_array($child)) {
      throw new XHPRenderArrayException('Can not render array!');
    } else {
      return htmlspecialchars((string)$child);
    }
  }

  public static function element2class($element) {
    return 'xhp_'.str_replace(array(':', '-'), array('__', '_'), $element);
  }

  public static function class2element($class) {
    return str_replace(array('__', '_'), array(':', '-'), preg_replace('#^xhp_#i', '', $class));
  }
}

abstract class :x:composable-element extends :x:base {
  private
    $attributes = array(),
	$children = array();

  public $source = "";

  private static $specialAttributes = array('data' => true, 'aria' => true);

  // Private constants indicating the declared types of attributes
  const TYPE_STRING   = 1;
  const TYPE_BOOL     = 2;
  const TYPE_NUMBER   = 3;
  const TYPE_ARRAY    = 4;
  const TYPE_OBJECT   = 5;
  const TYPE_VAR      = 6;
  const TYPE_ENUM     = 7;
  const TYPE_FLOAT    = 8;
  const TYPE_CALLABLE = 9;

  protected function init() {}

  /**
   * A new :x:composable-element is instantiated for every literal tag
   * expression in the script.
   *
   * The following code:
   * $foo = <foo attr="val">bar</foo>;
   *
   * will execute something like:
   * $foo = new xhp_foo(array('attr' => 'val'), array('bar'));
   *
   * @param $attributes    map of attributes to values
   * @param $children      list of children
   */
  final public function __construct($attributes, $children) {
	// UNSAFE - thinks we have to call the parent constructor, which is abstract...
    foreach ($children as $child) {
      $this->appendChild($child);
    }
    if (:x:base::$ENABLE_VALIDATION) {
      // There is some cost to having defaulted unused arguments on a function
      // so we leave these out and get them with func_get_args().
      $args = func_get_args();
      if (isset($args[2])) {
        $this->source = "$args[2]:$args[3]";
      } else {
        $this->source =
          'You have ENABLE_VALIDATION on, but debug information is not being ' .
          'passed to XHP objects correctly. Ensure xhp.include_debug is on ' .
          'in your PHP configuration. Without this option enabled, ' .
          'validation errors will be painful to debug at best.';
      }
    }
    $this->setAttributes($attributes);
    $this->init();
  }

  /**
   * Adds a child to the end of this node. If you give an array to this method
   * then it will behave like a DocumentFragment.
   *
   * @param $child     single child or array of children
   */
  final public function appendChild($child) {
    if (is_array($child)) {
      foreach ($child as $c) {
        $this->appendChild($c);
      }
    } else if ($child instanceof :x:frag) {
      $this->children = array_merge($this->children, $child->children);
    } else if ($child !== null) {
      $this->children[] = $child;
    }
    return $this;
  }

  /**
   * Adds a child to the beginning of this node. If you give an array to this
   * method then it will behave like a DocumentFragment.
   *
   * @param $child     single child or array of children
   */
  final public function prependChild($child) {
    if (is_array($child)) {
      foreach (array_reverse($child) as $c) {
        $this->prependChild($c);
      }
    } else if ($child instanceof :x:frag) {
      $this->children = array_merge($child->children, $this->children);
    } else if ($child !== null) {
      array_unshift($this->children, $child);
    }
    return $this;
  }

  /**
   * Replaces all children in this node. You may pass a single array or
   * multiple parameters.
   *
   * @param $children  Single child or array of children
   */
  final public function replaceChildren(...) {
    // This function has been micro-optimized
    $args = func_get_args();
    $new_children = array();
    foreach ($args as $xhp) {
      if ($xhp) {
        if ($xhp instanceof :x:frag) {
          foreach ($xhp->children as $child) {
            $new_children[] = $child;
          }
        } else if (!is_array($xhp)) {
          $new_children[] = $xhp;
        } else {
          foreach ($xhp as $element) {
            if ($element instanceof :x:frag) {
              foreach ($element->children as $child) {
                $new_children[] = $child;
              }
            } else if ($element !== null) {
              $new_children[] = $element;
            }
          }
        }
      }
    }
    $this->children = $new_children;
    return $this;
  }

  /**
   * Fetches all direct children of this element that match a particular tag
   * name or category (or all children if none is given)
   *
   * @param $selector   tag name or category (optional)
   * @return array
   */
  final protected function getChildren($selector = null) {
    if (!$selector) {
      return $this->children;
    }
    $result = array();
    if ($selector[0] == '%') {
      $selector = substr($selector, 1);
      foreach ($this->children as $child) {
        if ($child instanceof :x:base && $child->categoryOf($selector)) {
          $result[] = $child;
        }
      }
    } else {
      $selector = :x:base::element2class($selector);
      foreach ($this->children as $child) {
        if ($child instanceof $selector) {
          $result[] = $child;
        }
      }
    }
    return $result;
  }


  /**
   * Fetches the first direct child of the element, or the first child that
   * matches the tag if one is given
   *
   * @param $selector   string   tag name or category (optional)
   * @return            element  the first child node (with the given selector),
   *                             false if there are no (matching) children
   */
  final protected function getFirstChild($selector = null) {
    if (!$selector) {
      return reset($this->children);
    } else if ($selector[0] == '%') {
      $selector = substr($selector, 1);
      foreach ($this->children as $child) {
        if ($child instanceof :x:base && $child->categoryOf($selector)) {
          return $child;
        }
      }
    } else {
      $selector = :x:base::element2class($selector);
      foreach ($this->children as $child) {
        if ($child instanceof $selector) {
          return $child;
        }
      }
    }
    return null;
  }

  /**
   * Fetches the last direct child of the element, or the last child that
   * matches the tag or category if one is given
   *
   * @param $selector  string   tag name or category (optional)
   * @return           element  the last child node (with the given selector),
   *                            false if there are no (matching) children
   */
  final protected function getLastChild($selector = null) {
    $temp = $this->getChildren($selector);
    return end($temp);
  }

  /**
   * Returns true if the attribute is a data- or aria- attribute.
   *
   * @param $attr      attribute to fetch
   * @return           bool
   */
  final private static function isAttributeSpecial($attr) {
    // Must be at least 6 characters, with a '-' in the 5th position
    return
      isset($attr[5])
      && $attr[4] == '-'
      && isset(self::$specialAttributes[substr($attr, 0, 4)]);
  }

  /**
   * Fetches an attribute from this elements attribute store. If $attr is not
   * defined in the store and is not a data- or aria- attribute an exception
   * will be thrown. An exception will also be thrown if $attr is required and
   * not set.
   *
   * @param $attr      attribute to fetch
   * @return           value
   */
  final public function getAttribute($attr) {
    // Return the attribute if it's there
    if (isset($this->attributes[$attr])) {
      return $this->attributes[$attr];
    }

    if (!self::isAttributeSpecial($attr)) {
      // Get the declaration
      $decl = $this->__xhpAttributeDeclaration();

      if (!isset($decl[$attr])) {
        throw new XHPAttributeNotSupportedException($this, $attr);
      } else if (!empty($decl[$attr][3])) {
        throw new XHPAttributeRequiredException($this, $attr);
      } else {
        return $decl[$attr][2];
      }
    } else {
      return null;
    }
  }

  final public function getAttributes() {
    return $this->attributes;
  }

  /**
   * Sets an attribute in this element's attribute store. If the attribute is
   * not defined in the store and is not a data- or aria- attribute an
   * exception will be thrown. An exception will also be thrown if the
   * attribute value is invalid.
   *
   * @param $attr      attribute to set
   * @param $val       value
   */
  final public function setAttribute($attr, $value) {
    if (!self::isAttributeSpecial($attr)) {
      $this->validateAttributeValue($attr, $value);
    } else {
      $value = (string)$value;
    }
    $this->attributes[$attr] = $value;
    return $this;
  }

  /**
   * Takes an array of key/value pairs and adds each as an attribute.
   *
   * @param $attrs    array of attributes
   */
  final public function setAttributes(array $attrs) {
    foreach ($attrs as $key => $value) {
      $this->setAttribute($key, $value);
    }
    return $this;
  }

  /**
   * Whether the attribute has been explicitly set to a non-null value by the
   * caller (vs. using the default set by "attribute" in the class definition).
   *
   * @param $attr attribute to check
   */
  final public function isAttributeSet($attr) {
    return isset($this->attributes[$attr]);
  }

  /**
   * Removes an attribute from this element's attribute store. An exception
   * will be thrown if $attr is not supported.
   *
   * @param $attr      attribute to remove
   * @param $val       value
   */
  final public function removeAttribute($attr) {
    if (!self::isAttributeSpecial($attr)) {
		$value = null;
		$this->validateAttributeValue($attr, $value);
    }
    unset($this->attributes[$attr]);
    return $this;
  }
  
  /**
   * Sets an attribute in this element's attribute store. Always foregoes
   * validation.
   *
   * @param $attr      attribute to set
   * @param $val       value
   */
  final public function forceAttribute($attr, $value) {
    $this->attributes[$attr] = $value;
    return $this;
  }

  final protected function __flushElementChildren() {

    // Flush all :x:base elements to x:primitive's
    $ln = count($this->children);
    for ($ii = 0; $ii < $ln; ++$ii) {
      $child = $this->children[$ii];
      if ($child instanceof :x:element) {
        do {
          if (:x:base::$ENABLE_VALIDATION) {
            $child->validateChildren();
          }
          $child = $child->render();
        } while ($child instanceof :x:element);

        if (!($child instanceof :x:primitive)) {
          throw new XHPCoreRenderException($this->children[$ii], $child);
        }

        if ($child instanceof :x:frag) {
          array_splice($this->children, $ii, 1, $child->children);
          $ln = count($this->children);
          --$ii;
        } else {
          $this->children[$ii] = $child;
        }
      }
    }
  }

  /**
   * Defined in elements by the `attribute` keyword. The declaration is simple.
   * There is a keyed array, with each key being an attribute. Each value is
   * an array with 4 elements. The first is the attribute type. The second is
   * meta-data about the attribute. The third is a default value (null for
   * none). And the fourth is whether or not this value is required.
   *
   * Attribute types are suggested by the TYPE_* constants.
   */
  protected static function &__xhpAttributeDeclaration() {
    static $_ = array();
    return $_;
  }

  /**
   * Defined in elements by the `category` keyword. This is just a list of all
   * categories an element belongs to. Each category is a key with value 1.
   */
  protected function &__xhpCategoryDeclaration() {
    static $_ = array();
    return $_;
  }

  /**
   * Defined in elements by the `children` keyword. This returns a pattern of
   * allowed children. The return value is potentially very complicated. The
   * two simplest are 0 and 1 which mean no children and any children,
   * respectively. Otherwise you're dealing with an array which is just the
   * biggest mess you've ever seen.
   */
  protected function &__xhpChildrenDeclaration() {
    static $_ = 1;
    return $_;
  }

  /**
   * Throws an exception if $val is not a valid value for the attribute $attr
   * on this element.
   */
  final protected function validateAttributeValue($attr, &$val) {
    $decl = $this->__xhpAttributeDeclaration();
    if (!isset($decl[$attr])) {
      throw new XHPAttributeNotSupportedException($this, $attr);
    }
    if ($val === null) {
      return;
    }
    switch ($decl[$attr][0]) {
      case self::TYPE_STRING:
        $val = (string)$val;
        return;

      case self::TYPE_BOOL:
        if (!is_bool($val)) {
          if ($val === "false") {
            $val = false;
          } else {
            $val = (bool)$val;
          }
        }
        return;

      case self::TYPE_NUMBER:
        if (!is_int($val)) {
          $val = (int)$val;
        }
        return;

      case self::TYPE_FLOAT:
        if (!is_numeric($val)) {
          $val = (float)$val;
        }
        return;

      case self::TYPE_CALLABLE:
        if (!is_callable($val)) {
          throw new XHPInvalidAttributeException($this, 'callable', $attr, $val);
        }
        return;

      case self::TYPE_ARRAY:
        if (!is_array($val)) {
          throw new XHPInvalidAttributeException($this, 'array', $attr, $val);
        }
        if ($decl[$attr][1]) {
          if ($decl[$attr][1][0]) {
            if ($decl[$attr][1][0] == self::TYPE_STRING) {
              $type = 'string';
              $func = 'is_string';
            } else {
              $type = 'int';
              $func = 'is_int';
            }
            if (count($val) != count(array_filter(array_keys($val), $func))) {
              $bad = $type == 'string' ? 'int' : 'string';
              throw new XHPInvalidArrayKeyAttributeException($this, $type, $attr, $bad);
            }
          }
          switch ($decl[$attr][1][1]) {
            case self::TYPE_STRING:
              $type = 'string';
              $func = 'is_string';
              break;
            case self::TYPE_BOOL:
              $type = 'bool';
              $func = 'is_bool';
              break;
            case self::TYPE_NUMBER:
              $type = 'int';
              $func = 'is_int';
              break;
            case self::TYPE_FLOAT:
              $type = 'float';
              $func = 'is_numeric';
              break;
            case self::TYPE_ARRAY:
              $type = 'array';
              $func = 'is_array';
              break;
            case self::TYPE_OBJECT:
              $type = $decl[$attr][1][2];
              $func = function($item) use ($type) {
                return $item instanceof $type;
              };
              break;
          }
          $filtered = array_filter($val, $func);
          if (count($val) != count($filtered)) {
            $bad = array_diff($val, $filtered);
            throw new XHPInvalidArrayAttributeException($this, $type, $attr, reset($bad));
          }
        }
        return;

      case self::TYPE_OBJECT:
        if (!($val instanceof $decl[$attr][1])) {
          throw new XHPInvalidAttributeException(
            $this, $decl[$attr][1], $attr, $val
          );
        }
        return;

      // case self::TYPE_VAR: `var` (any type)

      case self::TYPE_ENUM:
        foreach ($decl[$attr][1] as $enum) {
          if ($enum === $val) {
            return;
          }
        }
        $enums = 'enum("' . implode('","', $decl[$attr][1]) . '")';
        throw new XHPInvalidAttributeException($this, $enums, $attr, $val);
    }
  }

  /**
   * Validates that this element's children match its children descriptor, and
   * throws an exception if that's not the case.
   */
  final protected function validateChildren() {
    $decl = $this->__xhpChildrenDeclaration();
    if ($decl === 1) { // Any children allowed
      return;
    }
    if ($decl === 0) { // No children allowed
      if ($this->children) {
        throw new XHPInvalidChildrenException($this, 0);
      } else {
        return;
      }
    }
    $ii = 0;
    if (!$this->validateChildrenExpression($decl, $ii) ||
        $ii < count($this->children)) {
      // Use of HTML() breaks the content model definition.
      // Lesson: Don't use HTML().
      if (isset($this->children[$ii]) && $this->children[$ii] instanceof HTML) {
        return;
      }
      throw new XHPInvalidChildrenException($this, $ii);
    }
  }

  final private function validateChildrenExpression($decl, &$index) {
    switch ($decl[0]) {
      case 0: // Exactly once -- :fb-thing
        if ($this->validateChildrenRule($decl[1], $decl[2], $index)) {
          return true;
        }
        return false;

      case 1: // Zero or more times -- :fb-thing*
        while ($this->validateChildrenRule($decl[1], $decl[2], $index));
        return true;

      case 2: // Zero or one times -- :fb-thing?
        if ($this->validateChildrenRule($decl[1], $decl[2], $index));
        return true;

      case 3: // One or more times -- :fb-thing+
        if (!$this->validateChildrenRule($decl[1], $decl[2], $index)) {
          return false;
        }
        while ($this->validateChildrenRule($decl[1], $decl[2], $index));
        return true;

      case 4: // Specific order -- :fb-thing, :fb-other-thing
        $oindex = $index;
        if ($this->validateChildrenExpression($decl[1], $index) &&
            $this->validateChildrenExpression($decl[2], $index)) {
          return true;
        }
        $index = $oindex;
        return false;

      case 5: // Either or -- :fb-thing | :fb-other-thing
        if ($this->validateChildrenExpression($decl[1], $index) ||
            $this->validateChildrenExpression($decl[2], $index)) {
          return true;
        }
        return false;
    }
  }

  final private function validateChildrenRule($type, $rule, &$index) {
    switch ($type) {
      case 1: // any element -- any
        if (isset($this->children[$index])) {
          ++$index;
          return true;
        }
        return false;

      case 2: // pcdata -- pcdata
        if (isset($this->children[$index]) &&
            !($this->children[$index] instanceof :x:base)) {
          ++$index;
          return true;
        }
        return false;

      case 3: // specific element -- :fb-thing
        if (isset($this->children[$index]) &&
            $this->children[$index] instanceof $rule) {
          ++$index;
          return true;
        }
        return false;

      case 4: // element category -- %block
        if (!isset($this->children[$index]) ||
            !($this->children[$index] instanceof :x:base)) {
          return false;
        }
        $categories = $this->children[$index]->__xhpCategoryDeclaration();
        if (empty($categories[$rule])) {
          return false;
        }
        ++$index;
        return true;

      case 5: // nested rule -- ((:fb-thing, :fb-other-thing)*, :fb:thing-footer)
        return $this->validateChildrenExpression($rule, $index);
    }
  }

  /**
   * Returns the human-readable `children` declaration as seen in this class's
   * source code.
   */
  final public function __getChildrenDeclaration() {
    $decl = $this->__xhpChildrenDeclaration();
    if ($decl === 1) {
      return 'any';
    }
    if ($decl === 0) {
      return 'empty';
    }
    return $this->renderChildrenDeclaration($decl);
  }

  final private function renderChildrenDeclaration($decl) {
    switch ($decl[0]) {
      case 0:
        return $this->renderChildrenRule($decl[1], $decl[2]);

      case 1:
        return $this->renderChildrenRule($decl[1], $decl[2]) . '*';

      case 2:
        return $this->renderChildrenRule($decl[1], $decl[2]) . '?';

      case 3:
        return $this->renderChildrenRule($decl[1], $decl[2]) . '+';

      case 4:
        return $this->renderChildrenDeclaration($decl[1]) . ',' .
          $this->renderChildrenDeclaration($decl[2]);

      case 5:
        return $this->renderChildrenDeclaration($decl[1]) . '|' .
          $this->renderChildrenDeclaration($decl[2]);
    }
  }

  final private function renderChildrenRule($type, $rule) {
    switch ($type) {
      case 1:
        return 'any';

      case 2:
        return 'pcdata';

      case 3:
        return ':' . :x:base::class2element($rule);

      case 4:
        return '%' . $rule;

      case 5:
        return '(' . $this->renderChildrenDeclaration($rule) . ')';
    }
  }

  /**
   * Returns a description of the current children in this element. Maybe
   * something like this:
   * <div><span>foo</span>bar</div> ->
   * :span[%inline],pcdata
   */
  final public function __getChildrenDescription() {
    $desc = array();
    foreach ($this->children as $child) {
      if ($child instanceof :x:base) {
        $tmp = ':' . :x:base::class2element(get_class($child));
        if ($categories = $child->__xhpCategoryDeclaration()) {
          $tmp .= '[%'. implode(',%', array_keys($categories)) . ']';
        }
        $desc[] = $tmp;
      } else {
        $desc[] = 'pcdata';
      }
    }
    return implode(',', $desc);
  }

  final public function categoryOf($c) {
    $categories = $this->__xhpCategoryDeclaration();
    if (isset($categories[$c])) {
      return true;
    }
    // XHP parses the category string
    $c = str_replace(array(':', '-'), array('__', '_'), $c);
    return isset($categories[$c]);
  }
}

/**
 * :x:primitive lays down the foundation for very low-level elements. You
 * should directly :x:primitive only if you are creating a core element that
 * needs to directly implement stringify(). All other elements should subclass
 * from :x:element.
 */
abstract class :x:primitive extends :x:composable-element {
  abstract protected function stringify();

  /**
   *  This isn't __toString() because throwing an exception out of __toString()
   *  produces a useless, immediate fatal, and allowing XHP to seamlessly cast
   *  into strings encourages bad practices, like this real snippet:
   *
   *    $links .= <a>...</a>;
   *    $links .= <a>...</a>;
   *    return HTML($links);
   *
   */
  final public function __toString() {

    // Validate our children
    $this->__flushElementChildren();
    if (:x:base::$ENABLE_VALIDATION) {
      try {
        $this->validateChildren();
      } catch (Exception $error) {
        trigger_error($error->getMessage(), E_USER_ERROR);
      }
    }

    // Render to string
    return $this->stringify();
  }
}

/**
 * :x:element defines an interface that all user-land elements should subclass
 * from. The main difference between :x:element and :x:primitive is that
 * subclasses of :x:element should implement `render()` instead of `stringify`.
 * This is important because most elements should not be dealing with strings
 * of markup.
 */
abstract class :x:element extends :x:composable-element {
  final public function __toString() {
    $that = $this;

    if (:x:base::$ENABLE_VALIDATION) {
      try {
        // Validate the current object
        $that->validateChildren();

        // And each intermediary object it returns
        while (($that = $that->render()) instanceof :x:element) {
          $that->validateChildren();
        }

        // render() must always return XHPPrimitives
        if (!($that instanceof :x:composable-element)) {
          throw new XHPCoreRenderException($this, $that);
        }
      } catch (Exception $error) {
        trigger_error($error->getMessage(), E_USER_ERROR);
      }
    } else {
      // Skip the above checks when not validating
      while (($that = $that->render()) instanceof :x:element);
    }

    return $that->__toString();
  }
}

/**
 * An <x:frag /> is a transparent wrapper around any number of elements. When
 * you render it just the children will be rendered. When you append it to an
 * element the <x:frag /> will disappear and each child will be sequentially
 * appended to the element.
 */
class :x:frag extends :x:primitive {
  protected function stringify() {
    $buf = '';
    foreach ($this->getChildren() as $child) {
      $buf .= :x:base::renderChild($child);
    }
    return $buf;
  }
}

/**
 * Exceptions are neat.
 */
class XHPException extends Exception {
  protected static function getElementName($that) {
    $name = get_class($that);
    if (substr($name, 0, 4) !== 'xhp_') {
      return $name;
    } else {
      return :x:base::class2element($name);
    }
  }
}

class XHPClassException extends XHPException {
  public function __construct($that, $msg) {
    parent::__construct(
      'Exception in class `' . XHPException::getElementName($that) . "`\n\n".
      "$that->source\n\n".
      $msg
    );
  }
}

class XHPCoreRenderException extends XHPException {
  public function __construct($that, $rend) {
    parent::__construct(
      ':x:element::render must reduce an object to an :x:primitive, but `'.
      :x:base::class2element(get_class($that)).'` reduced into `'.gettype($rend)."`.\n\n".
      $that->source
    );
  }
}

class XHPRenderArrayException extends XHPException {
}

class XHPInvalidArrayAttributeException extends XHPException {
  public function __construct($that, $type, $attr, $val) {
    if (is_object($val)) {
      $val_type = get_class($val);
    } else {
      $val_type = gettype($val);
    }
    parent::__construct(
      "Invalid attribute `$attr` of type array<`$val_type`> supplied to element `".
      :x:base::class2element(get_class($that))."`, expected array<`$type`>.\n\n".
      $that->source
    );
  }
}

class XHPInvalidArrayKeyAttributeException extends XHPException {
  public function __construct($that, $type, $attr, $val_type) {
    parent::__construct(
      "Invalid key in attribute `$attr` of type array<$val_type => ?> supplied to element `".
      :x:base::class2element(get_class($that))."`, expected array<$type => ?>.\n\n".
      $that->source
    );
  }
}

class XHPAttributeNotSupportedException extends XHPException {
  public function __construct($that, $attr) {
    parent::__construct(
      'Attribute "'.$attr.'" is not supported in class '.
      '"'.XHPException::getElementName($that).'"'.
      "\n\n".$that->source."\n\n".
      'Please check for typos in your attribute. If you are creating a new '.
      'attribute on this element define it with the "attribute" keyword'."\n\n"
    );
  }
}

class XHPAttributeRequiredException extends XHPException {
  public function __construct($that, $attr) {
    parent::__construct(
      'Required attribute `'.$attr.'` was not specified in element '.
      '`'.XHPException::getElementName($that)."`.\n\n".
      $that->source
    );
  }
}

class XHPInvalidAttributeException extends XHPException {
  public function __construct($that, $type, $attr, $val) {
    if (is_object($val)) {
      $val_type = get_class($val);
    } else {
      $val_type = gettype($val);
    }
    parent::__construct(
      "Invalid attribute `$attr` of type `$val_type` supplied to element `".
      :x:base::class2element(get_class($that))."`, expected `$type`.\n\n".
      $that->source
    );
  }
}

class XHPInvalidChildrenException extends XHPException {
  public function __construct($that, $index) {
    parent::__construct(
      'Element `'.XHPException::getElementName($that).'` was rendered with '.
      "invalid children.\n\n".
      "$that->source\n\n".
      "Verified $index children before failing.\n\n".
      "Children expected:\n".$that->__getChildrenDeclaration()."\n\n".
      "Children received:\n".$that->__getChildrenDescription()
    );
  }
}
