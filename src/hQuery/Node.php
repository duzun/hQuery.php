<?php
namespace duzun\hQuery;

use duzun\hQuery\Parser\Selector as SelectorParser;
use duzun\hQuery\Parser\HTML as HTMLParser;

// ------------------------------------------------------------------------
/**
 *  Base class for HTML Elements and Documents.
 *
 *  API Documentation at https://duzun.github.io/hQuery.php
 *
 *  @internal
 *  @license MIT
 */
abstract class Node implements \Iterator, \Countable
{
    // ------------------------------------------------------------------------
    const VERSION = '3.0.3';
    // ------------------------------------------------------------------------
    /**
     * Response details of last request
     * @var stdClass
     */
    public static $last_http_result;

    // ------------------------------------------------------------------------
    /**
     * @var duzun\hQuery\Node
     */
    public static $selected_doc = null;

    // ------------------------------------------------------------------------
    /**
     * Node properties
     * @var array
     */
    protected $_prop = array();

    /**
     * Parent doc
     * @var duzun\hQuery
     */
    protected $doc;

    /**
     * contained elements' IDs
     * @var array
     */
    protected $ids;

    /**
     * excluded elements' IDs
     * @var array
     */
    protected $exc;

    /**
     * @var array
     */
    protected static $_mb_encodings;

    // ------------------------------------------------------------------------
    /**
     * map tag names (eg ['b' => 'strong', 'i' => 'em'])
     * @var array
     */
    public $tag_map;

    // ------------------------------------------------------------------------
    // Memory efficiency tricks ;-)
    /**
     * @var array
     */
    static $_ar_ = array();
    /**
     * @var int
     */
    static $_mi_ = PHP_INT_MAX;
    /**
     * @var null
     */
    static $_nl_ = null;
    /**
     * @var boolean
     */
    static $_fl_ = false;
    /**
     * @var boolean
     */
    static $_tr_ = true;

    // ------------------------------------------------------------------------
    /**
     * @param $doc
     * @param $ids
     * @param $is_ctx
     */
    protected function __construct($doc, $ids, $is_ctx = false)
    {
        $this->doc = $doc;
        if (is_int($ids)) {
            $ids = array($ids => $doc->ids[$ids]);
        }

        $this->ids = $is_ctx ? $this->_ctx_ids($ids) : $ids;
        if ($doc === $this) {
            // documents have no $doc property
            unset($this->doc);
            self::$selected_doc = $this;
        }
    }

    public function __destruct()
    {
        if (self::$selected_doc === $this) {
            self::$selected_doc = self::$_nl_;
        }

        $this->ids = self::$_nl_; // If any reference exists, destroy its contents! P.S. Might be buggy, but hey, I own this property. Sincerely yours, hQuery_Node class.
        unset($this->doc, $this->ids);
    }

    // ------------------------------------------------------------------------
    /**
     * Get and attribute or all attributes of first element in the collection.
     *
     * @param  string       $attr   attribute name, or NULL to get all
     * @param  boolean      $to_str When $attr is NULL, if true, get the list of attributes as string
     * @return array|string If no $attr, return a list of attributes, or attribute's value otherwise.
     */
    public function attr($attr = null, $to_str = false)
    {
        $k = key($this->ids);
        if (null === $k) {
            reset($this->ids);
            $k = key($this->ids);
        }
        return isset($k) ? $this->doc()->get_attr_byId($k, $attr, $to_str) : null;
    }

    // ------------------------------------------------------------------------

    /**
     * @deprecated
     * @return boolean
     */
    public function is_empty()
    {
        return $this->isEmpty();
    }

    /**
     * @return boolean
     */
    public function isEmpty()
    {
        return empty($this->ids);
    }

    /**
     * @return boolean
     */
    public function isDoc()
    {
        return !isset($this->doc) || $this === $this->doc;
    }

    /**
     * Get parent doc of this node.
     *
     * @return duzun\hQuery
     */
    public function doc()
    {
        return isset($this->doc) ? $this->doc : $this;
    }

    /**
     *  Finds a collection of nodes inside current document/context (similar to jQuery.fn.find()).
     *
     * @param  string         $sel       A valid CSS selector (some pseudo-selectors supported).
     * @param  array|string   $attr      OPTIONAL attributes as string or key-value pairs.
     * @return hQuery_Element collection of matched elements or NULL
     */
    public function find($sel, $attr = null)
    {
        return $this->doc()->find($sel, $attr, $this);
    }

    /**
     * @param  string           $sel  A valid CSS selector (some pseudo-selectors supported).
     * @param  array|string     $attr OPTIONAL attributes as string or key-value pairs.
     * @return hQuery_Element
     */
    public function exclude($sel, $attr = null)
    {
        $e = $this->find($sel, $attr, $this);
        if ($e) {
            if (empty($this->exc)) {
                $this->exc = $e->ids;
            } else {
                // foreach($e->ids as $b => $e) $this->exc[$b] = $e;
                $this->exc = $e->ids + $this->exc;
                ksort($this->exc);
            }
        }
        return $e;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        // doc
        if ($this->isDoc()) {
            return $this->html;
        }

        $ret = '';
        $doc = $this->doc;
        $ids = $this->ids;
        if (!empty($this->exc)) {
            $ids = array_diff_key($ids, $this->exc);
        }
        foreach ($ids as $p => $q) {
            // if(isset($this->exc, $this->exc[$p])) continue;
            ++$p;
            if ($p < $q) {
                $ret .= substr($doc->html, $p, $q - $p);
            }

        }
        return $ret;
    }

    // ------------------------------------------------------------------------
    /**
     * @return string .innerHTML
     */
    public function html($id = null)
    {
        if ($this->isDoc()) {
            return $this->html;
        }
        // doc

        $id = $this->_my_ids($id);
        if (false === $id) {
            return self::$_fl_;
        }

        $doc = $this->doc;
        if (!empty($this->exc)) {
            $id = array_diff_key($id, $this->exc);
        }

        $ret = self::$_nl_;
        foreach ($id as $p => $q) {
            // if(isset($this->exc, $this->exc[$p])) continue;
            ++$p;
            if ($p < $q) {
                $ret .= substr($doc->html, $p, $q - $p);
            }

        }
        return $ret;
    }

    /**
     * @return string .outerHtml
     */
    public function outerHtml($id = null)
    {
        $dm = $this->isDoc() && !isset($id);
        if ($dm) {
            return $this->html;
        }
        // doc

        $id = $this->_my_ids($id);
        if (false === $id) {
            return self::$_fl_;
        }
        $doc = $this->doc();
        $ret = self::$_nl_;
        $map = isset($this->tag_map) ? $this->tag_map : (isset($doc->tag_map) ? $doc->tag_map : null);
        foreach ($id as $p => $q) {
            $a = $doc->get_attr_byId($p, null, true);
            $n = $doc->tags[$p];
            if ($map && isset($map[$_n = strtolower($n)])) {
                $n = $map[$_n];
            }
            $h = $p++ == $q ? false : ($p < $q ? substr($doc->html, $p, $q - $p) : '');
            $ret .= '<' . $n . ($a ? ' ' . $a : '') . (false === $h ? ' />' : '>' . $h . '</' . $n . '>');
        }
        return $ret;
    }

    /**
     * @return string .innerText
     */
    public function text($id = null)
    {
        return html_entity_decode(strip_tags($this->html($id)), ENT_QUOTES); /* ??? */
    }

    /**
     * Parse .text as a definition list.
     *
     * @param  string         $sep key-value separator (default ":")
     * @param  string|Closure $key search for one specific key
     * @return array|mixed    the value for $key or list of key-value if no $key
     */
    public function text2dl($sep = ':', $key = null)
    {
        return self::text_parse_dl($this, $sep, $key);
    }

    /**
     * Definition list parser
     *
     * @param  $                      $dl     dl element
     * @param  String|RegExp|Function $key    search for one specific key
     * @param  String                 $dt_sel dt selector (default "dt")
     * @param  String                 $dd_sel dd selector (default "dd")
     * @param  String                 $dw     definition term wrapper selector (default none)
     * @return Object|mixed           the value for $key or list of key-value if no $key
     */
    public function dl($dt_sel = 'dt', $dd_sel = 'dd', $dw = null, $key = null)
    {
        $oneKey = isset($key);
        $dl     = $oneKey ? null : array();

        if ($dw) {
            $l = $this->find($dw);
            if ($l) {
                foreach ($l as $i => $w) {
                    if ($dt_sel) {
                        $dte = $w->find($dt_sel)->first();
                        $dt  = $dte->text();
                        if ($dd_sel) {
                            $dd = $w->find($dd_sel)->first()->text();
                        }
                        // @TODO
                        // else {
                        //     $dd = $w.contents().not($dte).text();
                        // }
                    } else {
                        if (!$dd_sel) {
                            $dd = explode(':', $w->text(), 2);
                            $dt = reset($dd);
                            $dd = end($dd);
                        }
                        // @TODO
                        // else {
                        //     $dd_ = $w->find($dd_sel)->first();
                        //     $dd = $dd_->text();
                        //     $dt = $w.contents().not($dd_).text();
                        // }
                    }
                    $dd = trim($dd);
                    $dt = trim($dt);
                    if ($oneKey) {
                        if ($key instanceof \Closure ? $key($dt, $dd) : $key == $dt) {
                            return $dd;
                        }
                    } else {
                        $dl[$dt] = $dd;
                    }
                }
            }

        } else {
            $dtl = $this->find($dt_sel);
            $ddl = $this->find($dd_sel)->toArray();
            $dd  = reset($ddl);
            foreach ($dtl as $i => $e) {
                $dt = trim($e->text());
                $dd = trim($dd->text());
                if ($oneKey) {
                    if ($key instanceof \Closure ? $key($dt, $dd) : $key == $dt) {
                        return $dd;
                    }
                } else {
                    $dl[$dt] = $dd;
                }
                $dd = next($ddl);
                if (!$dd) {
                    break;
                }

            }
        }

        return $dl;
    }

    /**
     * @return string .nodeName
     */
    public function nodeName($caseFolding = null, $id = null)
    {
        if (!isset($caseFolding)) {
            $caseFolding = HTML_Index::$case_folding;
        }

        $dm = $this->isDoc() && !isset($id);
        if ($dm) {
            $ret = array_unique($this->tags);
        }
        // doc
        else {
            $id = $this->_my_ids($id, true);
            if (false === $id) {
                return self::$_fl_;
            }

            $ret = self::array_select($this->doc()->tags, $id);
        }
        if ($caseFolding) {
            foreach ($ret as $i => $n) {
                $ret[$i] = strtolower($n);
            }

            if ($dm) {
                $ret = array_unique($ret);
            }

        }
        return count($ret) <= 1 ? reset($ret) : $ret;
    }

    // public function firstChild() {
    // $doc = $this->doc();
    // $q = reset($this->ids);
    // $p = key($this->ids);
    // return new Element($doc, array($p=>$q));
    // }

    // public function lastChild() {
    // $doc = $this->doc();
    // $q = end($this->ids);
    // $p = key($this->ids);
    // return new Element($doc, array($p=>$q));
    // }

    // ------------------------------------------------------------------------
    /**
     *  Get string offset of the first/current element
     *  in the source HTML document.
     *
     *   <div class="test"> Contents <span>of</span> #test </div>
     *                    |
     *                    pos()
     *
     * @param  boolean $restore - if true, restore internal pointer to previous position
     * @return int
     */
    public function pos($restore = true)
    {
        $k = key($this->ids);
        if (null === $k) {
            reset($this->ids);
            $k = key($this->ids);
            if (null !== $k && $restore) {
                end($this->ids);
                next($this->ids);
            }
        }
        return $k;
    }

    // ------------------------------------------------------------------------
    /**
     * Make a context array of ids:
     *     if x in $ids && exists y in $ids such that x in y then del x from $ids
     *
     * @return array ids
     */
    protected function _ctx_ids($ids = null)
    {
        $m   = -1;
        $exc = $this->exc;
        if (!isset($ids)) {
            $ids = $this->ids;
        } elseif (is_int($ids)) {
            $ids = isset($this->ids[$ids]) ? array($ids => $this->ids[$ids]) : self::$_fl_;
        } else {
            foreach ($ids as $b => $e) {
                if ($b <= $m || $b + 1 >= $e and empty($exc[$b])) {
                    unset($ids[$b]);
                } else {
                    $m = $e;
                }
            }
        }
        return $ids;
    }

    /**
     * Get all ids from inside of this element
     *
     * @return array ids
     */
    protected function _sub_ids($eq = false)
    {
        $ret = array();
        $ce  = reset($this->ids);
        $cb  = key($this->ids);
        $doc = $this->doc();
        foreach ($doc->ids as $b => $e) {
            if ($b < $cb || !$eq && $b == $cb) {
                continue;
            }

            if ($b < $ce) {
                $ret[$b] = $e;
            } else {
                $ce = next($this->ids);
                if (!$ce) {
                    // end of context
                    break;
                }
                $cb = key($this->ids);
            }
        }
        return $ret;
    }

    /**
     * Get and Normalize ids of $el
     *
     * @return array ids
     */
    protected function _doc_ids($el, $force_array = true)
    {
        if ($el instanceof self) {
            $el = $el->ids;
        }

        if ($force_array) {
            if (is_int($el)) {
                $el = array($el => $this->doc()->ids[$el]);
            }

            if (!is_array($el)) {
                throw new \Exception(__CLASS__ . '->' . __FUNCTION__ . ': not Array!');
            }

        }
        return $el;
    }

    /**
     * @param  array|int $id
     * @param  boolean   $keys
     * @return array
     */
    protected function _my_ids($id = null, $keys = false)
    {
        if (!isset($id)) {
            $id = $this->ids;
        } elseif (is_int($id)) {
            if (!isset($this->ids[$id])) {
                return self::$_fl_;
            }

            if ($keys) {
                return $id;
            }

            $id = array($id => $this->ids[$id]);
        } elseif (!$id) {
            return self::$_fl_;
        } else {
            ksort($id);
        }

        return $keys ? array_keys($id) : $id;
    }

    // ------------------------------------------------------------------------
    /**
     * @param  array|int $ids
     * @return array
     */
    protected function _parent($ids = null)
    {
        $ret = self::$_ar_;
        $ids = $this->_my_ids($ids);
        if (!$ids) {
            return $ret;
        }

        $lb   = $le   = -1;  // last parent
        $ie   = reset($ids); // current child
        $ib   = key($ids);
        $dids = $this->doc()->ids;
        foreach ($dids as $b => $e) {
            // $b < $ib < $e
            // if current element is past current child and last parent is parent for current child
            if ($ib <= $b) {
                if ($ib < $le && $lb < $ib) {
                    $ret[$lb] = $le;
                }
                // while current element is past current child
                do {
                    $ie = next($ids);
                    if (false === $ie) {
                        $ib = -1;
                        break 2;}
                    $ib = key($ids);
                } while ($ib <= $b);
            }
            // here $b < $ib
            if ($ib < $e) {
                // [$b=>$e] is a parent of $ib
                $lb = $b;
                $le = $e;
            }
        }
        if ($ib <= $b && $ib < $le && $lb < $ib) {
            // while current element is past current child and last parent is parent for current child
            $ret[$lb] = $le;
        }
        return $ret;
    }

    /**
     * @param  array|int $ids
     * @param  int       $n
     * @return array
     */
    public function _children($ids = null, $n = null)
    {
        $ret = self::$_ar_;
        $ids = $this->_my_ids($ids);
        if (!$ids) {
            return $ret;
        }
        $doc = $this->doc();

        $dids = &$doc->ids;
        $le   = end($ids);
        if (current($dids) === false) {
            $ie = reset($dids);
        }

        $ib = key($dids);
        foreach ($ids as $b => $e) {
            // empty tag; min 3 chars are required for a tag - eg. <b>
            if ($b + 4 >= $e) {
                continue;
            }

            // child of prev element
            if ($b <= $le) {
                while ($b < $ib) {
                    if ($ie = prev($dids)) {
                        $ib = key($dids);
                    } else {
                        reset($dids);
                        break;
                    }
                }

            } else {
                if (false === $ie && $ib < $b) {
                    break;
                }

            }
            $le = $e;

            while ($ib <= $b) {
                if ($ie = next($dids)) {
                    $ib = key($dids);
                } else {
                    end($dids);
                    break;
                }
            }

            if ($b < $ib) {
                $i = 0;
                while ($ib < $e) {
                    if (isset($n)) {
                        if ($n == $i) {
                            $ret[$ib] = $ie;
                            break;
                        }
                        ++$i;
                    } else {
                        $ret[$ib] = $ie;
                    }

                    $lie = $ie < $e ? $ie : $e; // min($ie, $e)
                    while ($ib <= $lie) {
                        if ($ie = next($dids)) {
                            $ib = key($dids);
                        } else {
                            end($dids);
                            continue 3;
                        }
                    }
                }
            }
        }
        return $ret;
    }

    /**
     * @param  array|int $ids
     * @param  int       $idx
     * @param  int       $count
     * @return array
     */
    public function _next($ids = null, $idx = 0, $count = 1)
    {
        $ret = self::$_ar_;          // array()
        $ids = $this->_my_ids($ids); // ids to search siblings for
        if (!$ids) {
            return $ret;
        }

        $dids = &$this->doc()->ids;  // all elements in the doc
        $kb   = $le   = self::$_mi_; // [$lb=>$le] (last parent) now is 100% parent for any element
        $ke   = $lb   = -1;          // last parent
        $ie   = reset($ids);         // current child
        $ib   = key($ids);
        $e    = current($dids); // traverse starting from current position
        if (false !== $e) {
            do {$b = key($dids);} while (($ib <= $b || $e < $ib) && ($e = prev($dids)));
        }

        if (empty($e)) {
            $e = reset($dids);
        }
        // current element

        $pt = $st = $ret; // stacks: $pt - parents, $st - siblings limits

        while ($e) {
            $b = key($dids);

            /* 4) */
            if ($ib <= $b) {
                // if current element is past our child, then its siblings context is found
                if ($kb < $ke) {
                    $st[$kb] = $ke;
                }

                $kb = $ie;
                $ke = $le;

                $ib = ($ie = next($ids)) ? key($ids) : self::$_mi_; // $ie < $ib === no more children
                if ($ie < $ib) {
                    break;
                }
                // no more children, empty siblings context, search done!

                // pop from stack, if not empty
                while ($le < $ib && $pt) {
                    // if past current parent, pop another one from the stack
                    $le = end($pt);
                    unset($pt[$lb = key($pt)]); // there must be something in the stack anyway
                }
            }

            /* 3) */
            if ($b < $ib && $ib < $e) {
                // push the parents to their stack
                $pt[$lb] = $le;
                $lb      = $b;
                $le      = $e;
            }

            $e = next($dids);
        } // while

        if ($ke < $kb) {
            return $ret;
        }
        // no siblings contexts found!
        $st[$kb] = $ke;
        ksort($st);

        foreach ($st as $kb => $ke) {
            if (false !== $e) {
                do {$b = key($dids);} while ($kb < $b && ($e = prev($dids)));
            }

            if (empty($e)) {
                $e = reset($dids);
            }
            // current element
            do {
                $b = key($dids);

                // Found a child of $kb
                if ($kb < $b) {
                    // iterate next siblings
                    $i = 0;
                    $c = $count;
                    while ($b < $ke) {
                        if ($idx <= $i) {
                            $ret[$b] = $e;
                            if (!--$c) {
                                break;
                            }

                        } else {
                            ++$i;
                        }

                        // Skip all inner elements of $b (until past $e)
                        $lie = $e < $ke ? $e : $ke;
                        while ($b <= $lie && ($e = next($dids))) {
                            $b = key($dids);
                        }

                        if (!$e) {
                            $e = end($dids);
                            break;
                        }
                    }
                    break;
                }
            } while ($e = next($dids));
        }

        return $ret;
    }

    /**
     * @param  array|int $ids
     * @param  int       $n
     * @return array
     */
    public function _prev($ids = null, $n = 0)
    {
        $ret = self::$_ar_;          // array()
        $ids = $this->_my_ids($ids); // ids to search siblings for
        if (!$ids) {
            return $ret;
        }

        $dids = &$this->doc()->ids;  // all elements in the doc
        $kb   = $le   = self::$_mi_; // [$lb=>$le] (last parent) now is 100% parent for any element
        $ke   = $lb   = -1;          // last parent
        $ie   = reset($ids);         // current child
        $ib   = key($ids);
        $e    = current($dids); // traverse starting from current position
        if (false !== $e) {
            do {
                $b = key($dids);
            } while (($ib <= $b || $e < $ib) && ($e = prev($dids)));
        }

        if (empty($e)) {
            $e = reset($dids);
        }
        // current element

        $pt = $st = $ret; // stacks: $pt - parents, $st - siblings limits
        while ($e) {
            $b = key($dids);
/* 4) */if ($ib <= $b) {
                // if current element is past our child, then its siblings context is found
                if ($kb < $ke) {
                    $st[$kb] = $ke;
                }

                $kb = $lb;
                $ke = $ib;

                $ib = ($ie = next($ids)) ? key($ids) : self::$_mi_; // $ie < $ib === no more children
                if ($ie < $ib) {
                    break;
                }
                // no more children, empty siblings context, search done!

                // pop from stack, if not empty
                while ($le < $ib && $pt) {
                    // if past current parent, pop another one from the stack
                    $le = end($pt);
                    unset($pt[$lb = key($pt)]); // there must be something in the stack anyway
                }
            }

/* 3) */if ($b < $ib && $ib < $e) {
                // push the parents to their stack
                $pt[$lb] = $le;
                $lb      = $b;
                $le      = $e;
            }

            $e = next($dids);
        } // while

        if ($ke < $kb) {
            return $ret;
        }
        // no siblings contexts found!

        if (false !== $e) {
            do {$b = key($dids);} while ($kb < $b && ($e = prev($dids)));
        }

        if (empty($e)) {
            $e = reset($dids);
        }
        // current element

        $st[$kb] = $ke;
        ksort($st);
        $kb = reset($st);
        $ke = key($st);

        do {
            $b = key($dids);

/* 1) */if ($kb < $b) {
                // iterate next siblings
                $pt  = self::$_ar_;
                $lie = -1;
                while ($b < $ke) {
                    $pt[$b] = $e;
                    $lie    = $e < $ke ? $e : $ke;
                    while ($b <= $lie && ($e = next($dids))) {
                        $b = key($dids);
                    }

                    if (!$e) {
                        $e = end($dids);
                        break;
                    }
                }

                if ($pt) {
                    $c = count($pt);
                    $i = $n < 0 ? 0 : $c;
                    $i -= $n + 1;
                    if (0 <= $i && $i < $c) {
                        $pt            = array_slice($pt, $i, 1, true);
                        $ret[key($pt)] = reset($pt);
                    }
                }
                $pt = self::$_nl_;

                if (empty($st)) {
                    break;
                }
                // stack empty, no more children, search done!

                // pop from stack, if not empty
                if ($kb = reset($st)) {
                    unset($st[$ke = key($st)]);
                } else {
                    $kb = self::$_mi_;
                }
                // $ke < $kb === context empy

                // rewind back
                while ($kb < $b && ($e = prev($dids))) {
                    $b = key($dids);
                }

                if (!$e) {
                    $e = reset($dids);
                }
                // only for wrong context! - error
            }
        } while ($e = next($dids));

        return $ret;
    }

    /**
     * @param  array|int $ids
     * @return array
     */
    public function _all($ids = null)
    {
        $ret = self::$_ar_;
        $ids = $this->_my_ids($ids);
        if (!$ids) {
            return $ret;
        }

        return $this->doc()->_find('*', null, null, $ids);
    }

    /**
     * $el < $this, with $eq == true -> $el <= $this
     *
     * @param  $el
     * @param  $eq
     * @return mixed
     */
    public function _has($el, $eq = false)
    {
        if (is_int($el)) {
            $e = end($this->ids);
            if ($el >= $e) {
                return self::$_fl_;
            }

            foreach ($this->ids as $b => $e) {
                if ($el < $b) {
                    return self::$_fl_;
                }

                if ($el == $b) {
                    return $eq;
                }

                if ($el < $e) {
                    return self::$_tr_;
                }

            }
            return self::$_fl_;
        }
        if ($el instanceof self) {
            if ($el === $this) {
                return self::$_fl_;
            }

            $el = $el->ids;
        } else {
            $el = $this->_ctx_ids($this->_doc_ids($el, true));
        }

        foreach ($el as $b => $e) {
            if (!$this->_has($b)) {
                return self::$_fl_;
            }
        }

        return self::$_tr_;
    }

    /**
     * Filter all ids of $el that are contained in(side) $this->ids
     *
     * @param  hQuery_Node|array $el  A node or list of ids
     * @param  boolean           $eq  if false, filter strict contents, otherwise $el might be in $this->ids
     * @return hQuery_Node|array same type as $el
     */
    public function _filter_contains($el, $eq = false)
    {
        if ($el instanceof self) {
            $o = $el;
        }

        $el  = $this->_doc_ids($el);
        $ret = self::$_ar_;

        $lb = $le = -1;   // last parent
        $ie = reset($el); // current child
        $ib = key($el);
        foreach ($this->ids as $b => $e) {
            // skip up to first $el in $this
            while ($ib < $b || !$eq && $ib == $b) {
                $ie = next($el);
                if (false === $ie) {
                    $ib = -1;
                    break 2;
                }
                $ib = key($el);
            }
            // $b < $ib
            while ($ib < $e) {
                $ret[$ib] = $ie;
                $ie       = next($el);
                if (false === $ie) {
                    $ib = -1;
                    break 2;
                }
                $ib = key($el);
            }
        }
        if (!empty($o)) {
            $o      = clone $o;
            $o->ids = $ret;
            $ret    = $o;
        }
        return $ret;
    }

// - Magic ------------------------------------------------
    /**
     * @param  $name
     * @return mixed
     */
    public function __get($name)
    {
        if ($this->_prop && array_key_exists($name, $this->_prop)) {
            return $this->_prop[$name];
        }

        return $this->attr($name);
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        if (isset($value)) {
            return $this->_prop[$name] = $value;
        }

        $this->__unset($name);
    }

    /**
     * @param  $name
     * @return boolean
     */
    public function __isset($name)
    {
        return isset($this->_prop[$name]);
    }

    /**
     * @param $name
     */
    public function __unset($name)
    {
        unset($this->_prop[$name]);
    }

    // ------------------------------------------------------------------------
    // Countable:
    public function count()
    {
        return isset($this->ids) ? count($this->ids) : 0;
    }

    // ------------------------------------------------------------------------
    // Iterable:
    public function current()
    {
        $k = key($this->ids);
        if (null === $k) {
            return false;
        }

        return array($k => $this->ids[$k]);
    }

    public function valid()
    {
        return current($this->ids) !== false;
    }

    public function key()
    {
        return key($this->ids);
    }

    public function next()
    {
        return next($this->ids) !== false ? $this->current() : false;
    }

    public function prev()
    {
        return prev($this->ids) !== false ? $this->current() : false;
    }

    /**
     * @return array
     */
    public function rewind()
    {
        reset($this->ids);
        return $this->current();
    }

// - Helpers ------------------------------------------------

    // ------------------------------------------------------------------------
    /**
     * Textual definition list parser
     *
     * @param  $|string       $dl  text or Element containing definition list text
     * @param  string         $sep key-value separator (default ":")
     * @param  string|Closure $key search for one specific key
     * @return array|mixed    the value for $key or list of key-value if no $key
     */
    public static function text_parse_dl($dl, $sep = ':', $key = null)
    {
        // Get the textContents of $dl
        if ($dl instanceof self) {
            if (count($dl) > 1) {
                $ln = array();
                foreach ($dl as $o) {
                    $ln[] = $o->text();
                }

                $dl = implode("\n", $ln);
            } else {
                $dl = $dl->text();
            }
        }

        $dl = trim($dl);

        $oneKey = isset($key);
        $o      = $oneKey ? false : array();
        if (!$dl) {
            return $o;
        }

        $ln = explode("\n", $dl);
        if (!count($ln)) {
            return $o;
        }

        foreach ($ln as $i => $l) {
            $l = trim($l);
            if (!$l) {
                continue;
            }

            $l = explode($sep, $l, 2);
            $i = rtrim(reset($l));
            $l = ltrim(end($l));
            if ($oneKey) {
                if ($key instanceof \Closure ? $key($i, $l) : $key == $i) {
                    return $l;
                }
            } else {
                $o[$i] = $l;
            }
        }

        return $o;
    }

    // ------------------------------------------------------------------------
    /**
     * @deprecated
     *
     * @param  $str
     * @param  $case_folding
     * @param  true            $extended
     * @return mixed
     */
    public static function html_parseAttrStr($str, $case_folding = true, $extended = false)
    {
        return HTMLParser::parseAttrStr($str, $case_folding, $extended);
    }

    // ------------------------------------------------------------------------
    /**
     * @deprecated
     *
     * @param $attr
     * @param $quote
     */
    public static function html_attr2str($attr, $quote = '"')
    {
        return HTMLParser::attr2str($attr, $quote);
    }

    // ------------------------------------------------------------------------
    /**
     * @deprecated
     *
     * @param  $str
     * @param  $case_folding
     * @return array
     */
    public static function parseCSStr($str, $case_folding = true)
    {
        return HTMLParser::parseCssStr($str, $case_folding);
    }

    /**
     * @deprecated
     *
     * @param  $css
     * @return string
     */
    public static function CSSArr2Str($css)
    {
        return HTMLParser::css2str($css);
    }

    // ------------------------------------------------------------------------
    /**
     * Use duzun\hQuery\Parser::str_range() instead
     *
     * @deprecated
     *
     * @param $comp
     * @param $pos
     * @param $len
     */
    public static function str_range($comp, $pos = 0, $len = null)
    {
        return Parser::str_range($comp, $pos, $len);
    }

    // ------------------------------------------------------------------------
    /**
     * @param  $arr
     * @param  $keys
     * @param  $force_null
     * @return array
     */
    public static function array_select($arr, $keys, $force_null = false)
    {
        $ret = array();

        is_array($keys) or is_object($keys) or $keys = array($keys);
        foreach ($keys as $k) {
            if (isset($arr[$k])) {
                $ret[$k] = $arr[$k];
            } elseif ($force_null) {
                $ret[$k] = null;
            }

        }
        return $ret;
    }

    // ------------------------------------------------------------------------
    /**
     * @param $charset
     */
    public static function is_mb_charset_supported($charset)
    {
        if (!isset(self::$_mb_encodings)) {
            if (!function_exists('mb_list_encodings')) {
                return false;
            }

            self::$_mb_encodings = array_change_key_case(
                array_flip(mb_list_encodings()),
                CASE_UPPER
            );
        }
        return isset(self::$_mb_encodings[strtoupper($charset)]);
    }

    // ------------------------------------------------------------------------
    /**
     * @param  $a
     * @param  $to
     * @param  $from
     * @param  NULL    $use_mb
     * @return mixed
     */
    public static function convert_encoding($a, $to, $from = null, $use_mb = null)
    {
        /**
         * @var mixed
         */
        static $meth = null;

        isset($meth) or $meth = function_exists('mb_convert_encoding');

        if (!isset($use_mb)) {
            $use_mb = $meth && self::is_mb_charset_supported($to) && (!isset($from) || self::is_mb_charset_supported($from));
        } elseif ($use_mb && !$meth) {
            $use_mb = false;
        }
        isset($from) or $from = $use_mb ? mb_internal_encoding() : iconv_get_encoding('internal_encoding');

        if (is_array($a)) {
            $ret = array();
            foreach ($a as $n => $v) {
                $ret[is_string($n) ? self::convert_encoding($n, $to, $from, $use_mb) : $n] = is_string($v) || is_array($v) || $v instanceof stdClass
                    ? self::convert_encoding($v, $to, $from, $use_mb)
                    : $v;
            }
            return $ret;
        } elseif ($a instanceof stdClass) {
            $ret = (object) array();
            foreach ($a as $n => $v) {
                $ret->{is_string($n) ? self::convert_encoding($n, $to, $from, $use_mb) : $n} = is_string($v) || is_array($v) || $v instanceof stdClass
                    ? self::convert_encoding($v, $to, $from, $use_mb)
                    : $v;
            }
            return $ret;
        }
        return is_string($a) ? $use_mb ? mb_convert_encoding($a, $to, $from) : iconv($from, $to, $a) : $a;
    }

    // ------------------------------------------------------------------------
}

// ------------------------------------------------------------------------
// PSR-0 alias
class_exists('hQuery_Node', false) or class_alias('duzun\\hQuery\\Node', 'hQuery_Node', false);
