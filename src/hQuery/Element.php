<?php
namespace duzun\hQuery;

use duzun\hQuery\Parser\HTML;
// ------------------------------------------------------------------------
class_exists('duzun\\hQuery\\Node', false) or require_once __DIR__ . DIRECTORY_SEPARATOR . 'Node.php';

// ------------------------------------------------------------------------
/**
 *  Represents an HTML Element ( eg div, input etc )
 *  or a collection of elements ( eq jQuery([div, span, ...]) )
 */
class Element extends Node implements \ArrayAccess
{
    // ------------------------------------------------------------------------
    // Iterator
    /**
     * Iterator Cache
     * @var array of Element
     */
    protected $_ich = null;

    // ------------------------------------------------------------------------
    /**
     * @param  boolean $cch
     * @return array
     */
    public function toArray($cch = true)
    {
        if ($cch && isset($this->_ich) && count($this->ids) === count($this->_ich)) {
            return $this->_ich;
        }

        $ret = array();
        if ($cch) {
            foreach ($this->ids as $b => $e) {
                $ret[$b] = isset($this->_ich[$b])
                    ? $this->_ich[$b]
                    : ($this->_ich[$b] = new self($this->doc, array($b => $e)))
                ;
            }
        } else {
            foreach ($this->ids as $b => $e) {
                $ret[$b] = new self($this->doc, array($b => $e));
            }
        }
        return $ret;
    }

    // ------------------------------------------------------------------------
    /**
     * @param  string  $name
     * @return mixed
     */
    public function __get($name)
    {
        switch ($name) {
            case 'innerHTML':
            case 'html':return $this->html();
            case 'outerHtml':return $this->outerHtml();
            case 'textContent':
            case 'text':return $this->text();
            case 'attr':return $this->attr();
            case 'value':
            case 'val':return $this->val();
            case 'nodeName':return $this->nodeName(false);
            case 'parent':return $this->parent();
            case 'children':return $this->children();
            case 'nextElementSibling':return $this->nextElementSibling();
            case 'previousElementSibling':return $this->previousElementSibling();
            case 'className':$name = 'class';
                break;
        }
        // return parent::__get($name);

        if ($this->_prop && array_key_exists($name, $this->_prop)) {
            return $this->_prop[$name];
        }

        switch ($name) {
            case 'style':
                $style = $this->attr('style');
                if ( !$style ) return self::$_ar_;
                return $this->_prop[$name] = HTML::parseCssStr($style);

            case 'id':
            case 'class':
            case 'alt':
            case 'title':
            case 'src':
            case 'href':
            // case 'protocol':
            // case 'host':
            // case 'port':
            // case 'hostname':
            // case 'pathname':
            // case 'search':
            // case 'hash':
            default:
                return $this->attr($name);
        }
    }

    // ------------------------------------------------------------------------
    // ArrayAccess methods:

    /**
     * @param int|string $offset
     * @param mixed      $value
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            // $this->_data[] = $value; // ???
        }
        if (is_int($offset)) {
            $i = array_slice($this->ids, $offset, 1, true);
            // ??? can't manipulate collection's contents
        } else {
            $this->__set($offset, $value); // set a property
        }
    }

    /**
     * @param int|string $offset
     */
    public function offsetGet($offset)
    {
        return is_int($offset)
            ? $this->get($offset)    // an element from collection
         : $this->__get($offset) // a property
        ;
    }

    /**
     * @param  int|string $offset
     * @return boolean
     */
    public function offsetExists($offset)
    {
        if (is_int($offset)) {
            return 0 <= $offset && $offset < count($this->ids);
        }
        return $this->__isset($offset);
    }

    /**
     * @param int|string $offset
     */
    public function offsetUnset($offset)
    {
        if (is_int($offset)) {
            $i = array_slice($this->ids, $offset, 1, true);
            if ($i) {
                $i = key($i);
                unset($this->ids[$i], $this->_ich[$i]);
            }
        } else {
            unset($this->_prop[$offset]);
        }
    }

    // ------------------------------------------------------------------------
    /**
     * Override current() for iterations.
     *
     * @return hQuery_Element
     */
    public function current()
    {
        $k = key($this->ids);
        if (null === $k) {
            return false;
        }

        if (count($this->ids) == 1) {
            return $this;
        }

        if (!isset($this->_ich[$k])) {
            $this->_ich[$k] = new self($this->doc, array($k => $this->ids[$k]));
        }

        return $this->_ich[$k];
    }

    // ------------------------------------------------------------------------
    /**
     * Get value of an :input element.
     *
     * @return mixed value of the first element in the collection.
     */
    public function val()
    {
        $el = count($this) > 1 ? $this->get(0) : $this;
        switch (strtoupper($el->nodeName(false))) {
            case 'TEXTAREA':
                return $el->html();
            case 'INPUT':
                switch (strtoupper($el->attr('type'))) {
                    case 'CHECKBOX':if ($el->attr('checked') === false) {
                            return false;
                        }

                    default:return $el->attr('value');
                }
                return $el->html();
            case 'SELECT': // ???

            default:return false;
        }
    }

    /**
     * Checks whether $this element/collection has a(ll) class(es).
     *
     * @param  string|array $cl  - class(es) to check
     * @return boolean      true - has class, false - no class, - doesn't have any class,
     */
    public function hasClass($className)
    {
        $ret = $this->doc()->hasClass($this, $className);
        if (!is_array($ret)) {
            return $ret;
        }

        if (count($this) < 2) {
            return reset($ret);
        }

        return max($ret);
    }

    /**
     * Get the node at $idx position in the set, using cache
     *
     * @param  int              $idx - index of an element, starts with 0.
     * @return hQuery_Element
     */
    public function get($idx)
    {
        $i = array_slice($this->ids, $idx, 1, true);
        if (!$i) {
            return null;
        }

        // Try read cache first
        $k = key($i);
        if (isset($this->_ich[$k])) {
            return $this->_ich[$k];
        }

        // Create wraper instance for $i
        $o = new self($this->doc, $i);

        // Save to cache
        $this->_ich[$k] = $o;

        return $o;
    }

    /**
     * Get the node at $idx position in the set, no cache, each call creates new instance.
     *
     * @param  int              $idx - index of an element, starts with 0.
     * @return hQuery_Element
     */
    public function eq($idx)
    {
        $i = array_slice($this->ids, $idx, 1, true) or
        $i = array();
        // Create wraper instance for $i
        $o = new self($this->doc, $i);
        return $o;
    }

    /**
     * Get the first node in the set
     * @return hQuery_Element
     */
    public function first()
    {
        return $this->eq(0);
    }

    /**
     * Get the last node in the set
     * @return hQuery_Element
     */
    public function last()
    {
        return $this->eq(-1);
    }

    /**
     * Get a slice of current node collection.
     *
     * @param  int              $idx - start index of an element, starts with 0.
     * @param  int              $len - OPTIONAL number of element to slice. Defaults to all starting at $idx
     * @return hQuery_Element
     */
    public function slice($idx, $len = null)
    {
        $c = $this->count();
        if ($idx < $c) {
            $ids = array();
        } elseif (isset($len)) {
            if (0 == $idx && $len == $c) {
                return $this; // ???
                $ids = $this->ids;
            }
            $ids = array_slice($this->ids, $idx, $len, true);
        } else {
            if (0 == $idx) {
                return $this; // ???
                $ids = $this->ids;
            }
            $ids = array_slice($this->ids, $idx, $this->count(), true);
        }
        $o       = new self($this->doc, $ids);
        $o->_ich = &$this->_ich; // share iterator cache for iteration
        return $o;
    }

    /**
     * Get parent nodes for this collection of nodes.
     *
     * @return hQuery_Element parent
     */
    public function parent()
    {
        $p = $this->_parent();
        return $p ? new self($this->doc, $p) : null;
    }

    /**
     * Get child nodes for this collection of nodes.
     *
     * @return hQuery_Element children
     */
    public function children()
    {
        $p = $this->_children();
        return $p ? new self($this->doc, $p) : null;
    }

    /**
     * Get previous element siblings for each of the elements of this collection
     *
     * @return hQuery_Element previousElementSibling
     */
    public function previousElementSibling()
    {
        $p = $this->_prev();
        return $p ? new self($this->doc, $p) : null;
    }

    /**
     * Get next element siblings for each of the elements of this collection
     *
     * @return hQuery_Element nextElementSibling
     */
    public function nextElementSibling()
    {
        $p = $this->_next();
        return $p ? new self($this->doc, $p) : null;
    }

}

// ------------------------------------------------------------------------
// PSR-0 alias
class_exists('hQuery_Element', false) or class_alias('duzun\\hQuery\\Element', 'hQuery_Element', false);
