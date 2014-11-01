<?php
// ------------------------------------------------------------------------
/**
 *  Copyright (C) 2014 Dumitru Uzun
 *
 *  @author Dumitru Uzun (DUzun.ME)
 *  @ver 1.0.4
 */
// ------------------------------------------------------------------------

// ------------------------------------------------------------------------

/// Base class for all HTML Elements
abstract class ADOM_Node implements Iterator, Countable {
    static $_ar_ = array()     ;
    static $_mi_ = PHP_INT_MAX ;
    static $_nl_ = NULL        ;
    static $_fl_ = false       ;
    static $_tr_ = true        ;

    static $selected_doc = NULL;
    // ------------------------------------------------------------------------
    protected $_prop; // Properties
    protected $doc; // Parent doc
    protected $ids; // contained elements' IDs

    protected function __construct($doc, $ids, $is_ctx=false) {
        $this->doc = $doc;
        if(is_int($ids)) $ids = array($ids => $doc->ids[$ids]);
        $this->ids = $is_ctx ? $this->_ctx_ids($ids) : $ids;
        if($doc === $this) { // documents have no $doc property
            unset($this->doc);
            self::$selected_doc = $this;
        }
    }

    function __destruct() {
        if(self::$selected_doc === $this) self::$selected_doc = self::$_nl_;
        $this->ids = self::$_nl_; // If any reference exists, destroy its contents! P.S. Might be buggy, but hey, I own this property. Sincerly yours, ADOM_Node class.
        unset($this->doc, $this->ids);
    }
    // ------------------------------------------------------------------------
    // The magic of properties
    function __get($name) {
        if(array_key_exists($name, $this->_prop)) return $this->_prop[$name];
        return $this->attr($name);
    }
    function __set($name, $value) {
        if(isset($value)) return $this->_prop[$name] = $value;
        $this->__unset($name);
    }
    function __isset($name) {
        return isset($this->_prop[$name]);
    }
    function __unset($name) {
        unset($this->_prop[$name]);
    }
    // ------------------------------------------------------------------------
    function attr($attr=NULL, $to_str=false) {
        $k = key($this->ids);
        if($k === NULL) {
            reset($this->ids);
            $k = key($this->ids);
        }
        return isset($k) ? $this->doc()->get_attr_byId($k, $attr, $to_str) : NULL;
    }
    // ------------------------------------------------------------------------
    // Countable
    public function count() { return isset($this->ids) ? count($this->ids) : 0; }
    // ------------------------------------------------------------------------
    // Iterable
    function current() {
        $k = key($this->ids);
        if($k === NULL) return false;
        return array($k => $this->ids[$k]);
    }
    function valid()   { return current($this->ids) !== false; }
    function key()     { return key($this->ids); }
    function next()    { return next($this->ids) !== false ? $this->current() : false; }
    function prev()    { return prev($this->ids) !== false ? $this->current() : false; }
    function rewind()  { reset($this->ids); return $this->current(); }
    // ------------------------------------------------------------------------

    /**
     *  Get string offset of the first/current element
     *  in the source HTML document.
     */
    public function pos($rst=true) {
        $k = key($this->ids);
        if($k === NULL) {
            reset($this->ids);
            $k = key($this->ids);
            if($k !== NULL && $rst) {
                end($this->ids);
                next($this->ids);
            }
        }
        return $k;
    }


    function is_empty() {
        return empty($this->ids);
    }

    function isDoc() {
        return !isset($this->doc) || $this === $this->doc;
    }

    function doc() {
        return isset($this->doc) ? $this->doc : $this;
    }

    function find($sel, $attr=NULL) {
        return $this->doc()->find($sel, $attr, $this);
    }

    function __toString() {
        // doc
        if($this->isDoc()) return $this->html;

        $ret = '';
        $doc = $this->doc;
        foreach($this->ids as $p => $q) {
            if(isset($this->exc, $this->exc[$p])) continue;
            ++$p;
            if($p < $q) $ret .= substr($doc->html, $p, $q-$p);
        }
        return $ret;
    }

    /// Make a context array of ids (if x in $ids && exists y in $ids such that x in y then del x from $ids)
    protected function _ctx_ids($ids=NULL) {
        $m = -1;
        if(!isset($ids)) $ids = $this->ids;
        if(is_int($ids)) $ids = isset($this->ids[$ids]) ? array($ids => $this->ids[$ids]) : self::$_fl_;
        else foreach($ids as $b => $e) if($b <= $m || $b+1 >= $e) unset($ids[$b]); else $m = $e;
        return $ids;
    }

    /// Get all ids from inside of this element
    protected function _sub_ids($eq=false) {
        $ret = array();
        $ce  = reset($this->ids);
        $cb  = key($this->ids);
        $doc = $this->doc();
        foreach($doc->ids as $b => $e) {
            if($b < $cb || !$eq && $b == $cb) continue;
            if($b < $ce) {
                $ret[$b] = $e;
            }
            else {
                $ce = next($this->ids);
                if(!$ce) break; // end of context
                $cb = key($this->ids);
            }
        }
        return $ret;
    }

    /// Get and Normalize ids of $el
    protected function _doc_ids($el, $force_array=true) {
        if($el instanceof self) $el = $el->ids;
        if($force_array) {
            if(is_int($el)) $el = array($el=>$this->doc()->ids[$el]);
            if(!is_array($el)) throw new Exception(__CLASS__ . '->' . __FUNCTION__ . ': not Array!');
        }
        return $el;
    }

    protected function _my_ids($id=NULL, $keys=false) {
        if(!isset($id)) $id = $this->ids;
        elseif(is_int($id)) {
            if(!isset($this->ids[$id])) return self::$_fl_;
            if($keys) return $id;
            $id = array($id => $this->ids[$id]);
        }
        elseif(!$id) return self::$_fl_;
        else ksort($id);
        return $keys ? array_keys($id) : $id;
    }
    // ------------------------------------------------------------------------
    function _parent($ids=NULL, $n=0) {
        $ret = self::$_ar_;
        $ids = $this->_my_ids($ids);
        if(!$ids) return $ret;

        $lb = $le = -1;    // last parent
        $ie = reset($ids); // current child
        $ib = key($ids);
        $dids = &$this->doc()->ids;
        foreach($dids as $b => $e) { // $b < $ib < $e
            // if current element is past current child and last parent is parent for current child
            if($ib <= $b) {
                if($ib < $le && $lb < $ib) {
                    $ret[$lb] = $le;
                }
                // while current element is past current child
                do {
                    $ie = next($ids);
                    if($ie === false) { $ib = -1; break 2; }
                    $ib = key($ids);
                } while($ib <= $b);
            }
            // here $b < $ib
            if($ib < $e) { // [$b=>$e] is a parent of $ib
                $lb = $b;
                $le = $e;
            }
        }
        if($ib <= $b && $ib < $le && $lb < $ib) { // while current element is past current child and last parent is parent for current child
            $ret[$lb] = $le;
        }
        return $ret;
    }

    function _children($ids=NULL, $n=NULL) {
        $ret = self::$_ar_;
        $ids = $this->_my_ids($ids);
        if(!$ids) return $ret;

        $dids = &$this->doc()->ids;
        $le = end($ids);
        if(current($dids) === false) $ie = reset($dids);
        $ib = key($dids);
        foreach($ids as $b => $e) {
            if($b+4 >= $e) continue; // empty tag; min 3 chars are required for a tag - eg. <b>

            if($b <= $le) {          // child of prev element
                while($b < $ib) if($ie = prev($dids)) $ib = key($dids); else { reset($dids); break; }
            }
            else {
                if($ie === false && $ib < $b) break;
            }
            $le = $e;

            while($ib <= $b) {
                if($ie = next($dids)) $ib = key($dids);
                else { end($dids); break; }
            }

            if($b < $ib) {
                $i = 0;
                while($ib < $e) {
                    if(!isset($n)) $ret[$ib] = $ie;
                    elseif($n == $i) { $ret[$ib] = $ie; break; }
                    else ++$i;
                    $lie = $ie < $e ? $ie : $e;
                    while($ib <= $lie) {
                        if($ie = next($dids)) $ib = key($dids);
                        else { end($dids); continue 3; }
                    }
                }
            }
        }
        return $ret;
    }

   function _next($ids=NULL, $n=0) {
      $ret = self::$_ar_;              // array()
      $ids = $this->_my_ids($ids);     // ids to search siblings for
      if(!$ids) return $ret;

      $dids = &$this->doc()->ids;      // all elements in the doc
      $kb = $le = self::$_mi_;         // [$lb=>$le] (last parent) now is 100% parent for any element
      $ke = $lb = -1;                  // last parent
      $ie = reset($ids);               // current child
      $ib = key($ids);
      $e = current($dids);             // traverse starting from current position
      if($e !== false) do { $b = key($dids); } while( ($ib <= $b || $e < $ib) && ($e = prev($dids)) ) ;
      if(empty($e)) $e = reset($dids); // current element

      $pt = $st = $ret;                // stacks: $pt - parents, $st - siblings limits

      while($e) {
         $b = key($dids);
/* 4) */ if($ib <= $b) { // if current element is past our child, then its siblings context is found
           if($kb < $ke) $st[$kb] = $ke;
           $kb = $ie;
           $ke = $le;

           $ib = ($ie = next($ids)) ? key($ids) : self::$_mi_; // $ie < $ib === no more children
           if($ie < $ib) break; // no more children, empty siblings context, search done!

           // pop from stack, if not empty
           while($le < $ib && $pt) { // if past current parent, pop another one from the stack
              $le = end($pt);
              unset($pt[$lb = key($pt)]); // there must be something in the stack anyway
           }
         }

/* 3) */ if($b < $ib && $ib < $e) { // push the parents to their stack
           $pt[$lb] = $le;
           $lb = $b;
           $le = $e;
         }

         $e = next($dids);
      } // while

      if($ke < $kb) return $ret; // no siblings contexts found!
      $st[$kb] = $ke;
      ksort($st);

      foreach($st as $kb => $ke) {
        if($e !== false) do { $b = key($dids); } while( $kb < $b && ($e = prev($dids)) ) ;
        if(empty($e)) $e = reset($dids); // current element
        do {
           $b = key($dids);
           if($kb < $b) {
             // iterate next siblings
             $i = 0;
             while($b < $ke) {
               if($n == $i) { $ret[$b] = $e; break; } else ++$i;
               $lie = $e < $ke ? $e : $ke;
               while($b <= $lie && ($e = next($dids))) $b = key($dids);
               if(!$e) { $e = end($dids); break; }
             }
             break;
           }
        } while($e = next($dids));
      }

      return $ret;
   }

   function _prev($ids=NULL, $n=0) {
        $ret = self::$_ar_;              // array()
        $ids = $this->_my_ids($ids);     // ids to search siblings for
        if(!$ids) return $ret;

        $dids = &$this->doc()->ids;      // all elements in the doc
        $kb = $le = self::$_mi_;         // [$lb=>$le] (last parent) now is 100% parent for any element
        $ke = $lb = -1;                  // last parent
        $ie = reset($ids);               // current child
        $ib = key($ids);
        $e = current($dids);             // traverse starting from current position
        if($e !== false) do {
            $b = key($dids);
        } while( ($ib <= $b || $e < $ib) && ($e = prev($dids)) ) ;
        if(empty($e)) $e = reset($dids); // current element

      $pt = $st = $ret;                // stacks: $pt - parents, $st - siblings limits
      while($e) {
         $b = key($dids);
/* 4) */ if($ib <= $b) { // if current element is past our child, then its siblings context is found
           if($kb < $ke) $st[$kb] = $ke;
           $kb = $lb;
           $ke = $ib;

           $ib = ($ie = next($ids)) ? key($ids) : self::$_mi_; // $ie < $ib === no more children
           if($ie < $ib) break; // no more children, empty siblings context, search done!

           // pop from stack, if not empty
           while($le < $ib && $pt) { // if past current parent, pop another one from the stack
              $le = end($pt);
              unset($pt[$lb = key($pt)]); // there must be something in the stack anyway
           }
         }

/* 3) */ if($b < $ib && $ib < $e) { // push the parents to their stack
           $pt[$lb] = $le;
           $lb = $b;
           $le = $e;
         }

         $e = next($dids);
      } // while

      if($ke < $kb) return $ret; // no siblings contexts found!

      if($e !== false) do { $b = key($dids); } while( $kb < $b && ($e = prev($dids)) ) ;
      if(empty($e)) $e = reset($dids); // current element

      $st[$kb] = $ke;
      ksort($st);
      $kb = reset($st);
      $ke = key($st);

      do {
         $b = key($dids);

/* 1) */ if($kb < $b) {
           // iterate next siblings
           $pt = self::$_ar_;
           $lie = -1;
           while($b < $ke) {
             $pt[$b] = $e;
             $lie = $e < $ke ? $e : $ke;
             while($b <= $lie && ($e = next($dids))) $b = key($dids);
             if(!$e) { $e = end($dids); break; }
           }

           if($pt) {
              $c  = count($pt);
              $i  = $n < 0 ? 0 : $c;
              $i -= $n + 1;
              if(0 <= $i && $i < $c) {
                 $pt = array_slice($pt, $i, 1, true);
                 $ret[key($pt)] = reset($pt);
              }
           }
           $pt = self::$_nl_;

           if(empty($st)) break; // stack empty, no more children, search done!

           // pop from stack, if not empty
           if($kb = reset($st)) unset($st[$ke = key($st)]);
           else $kb = self::$_mi_; // $ke < $kb === context empy

           // rewind back
           while($kb < $b && ($e = prev($dids))) $b = key($dids);
           if(!$e) $e = reset($dids); // only for wrong context! - error
         }
      } while($e = next($dids));

      return $ret;
   }

   function _all($ids=NULL) {
      $ret = self::$_ar_;
      $ids = $this->_my_ids($ids);
      if(!$ids) return $ret;

      return $this->doc()->_find('*', NULL, NULL, $ids);
   }

    /// $el < $this, with $eq == true -> $el <= $this
    function _has($el, $eq=false) {
       if(is_int($el)) {
          $e = end($this->ids);
          if($el >= $e) return self::$_fl_;
          foreach($this->ids as $b => $e) {
             if($el <  $b) return self::$_fl_;
             if($el == $b) return $eq;
             if($el <  $e) return self::$_tr_;
          }
          return self::$_fl_;
       }
       if($el instanceof self) { if($el === $this) return self::$_fl_; $el = $el->ids; } else
       $el = $this->_ctx_ids($this->_doc_ids($el, true));
       foreach($el as $b => $e) if(!$this->has($b)) return self::$_fl_;
       return self::$_tr_;
    }

    /// filter all ids of $el that are contained in $this->ids
    function _filter($el, $eq=false) {
      if($el instanceof self) $o = $el;
      $el = $this->_doc_ids($el);
      $ret = self::$_ar_;

      $lb = $le = -1;    // last parent
      $ie = reset($el); // current child
      $ib = key($el);
      foreach($this->ids as $b => $e) {
         while($ib < $b || !$eq && $ib == $b) {
           $ie = next($el);
           if($ie === false) { $ib = -1; break 2; }
           $ib = key($el);
         }
         // $b < $ib
         while($ib < $e) {
           $ret[$ib] = $ie;
           $ie = next($el);
           if($ie === false) { $ib = -1; break 2; }
           $ib = key($el);
         }
      }
      if(!empty($o)) {
         $o = clone $o;
         $o->ids = $ret;
         $ret = $o;
      }
      return $ret;
    }
   // ------------------------------------------------------------------------

   /// innerHTML
   function html($id=NULL) {
      if($this->isDoc()) return $this->html; // doc

      $id = $this->_my_ids($id);
      if($id === false) return self::$_fl_;
      $doc = $this->doc;
      $ret = self::$_nl_;
      foreach($id as $p => $q) {
         if(isset($this->exc, $this->exc[$p])) continue;
         ++$p;
         if($p<$q) $ret .= substr($doc->html, $p, $q-$p);
      }
      return $ret;
   }

   function outerHtml($id=NULL) {
      $dm = $this->isDoc() && !isset($id);
      if($dm) return $this->html; // doc

      $id = $this->_my_ids($id);
      if($id === false) return self::$_fl_;
      $doc = $this->doc();
      $ret = self::$_nl_;
      $map = isset($this->tag_map) ? $this->tag_map : (isset($doc->tag_map) ? $doc->tag_map : NULL);
      foreach($id as $p => $q) {
         $a = $doc->get_attr_byId($p, NULL, true);
         $n = $doc->tags[$p];
         if($map && isset($map[$_n=strtolower($n)])) $n = $map[$_n];
         $h = $p++ == $q ? false : ($p<$q ? substr($doc->html, $p, $q-$p) : '');
         $ret .= '<'.$n.($a?' '.$a:'') . ($h === false ? ' />' : '>' . $h . '</'.$n.'>');
      }
      return $ret;
   }

    /// innerText
    function text($id=NULL) {
        return html_entity_decode(strip_tags($this->html($id)), ENT_QUOTES);/* ??? */
    }

   function nodeName($caseFolding = NULL, $id=NULL) {
      if(!isset($caseFolding)) $caseFolding = CHTML_Parser_Doc::$case_folding;
      $dm = $this->isDoc() && !isset($id);
      if($dm) $ret = array_unique($this->tags); // doc
      else {
         $id = $this->_my_ids($id, true);
         if($id === false) return self::$_fl_;
         $ret = self::array_select($this->doc()->tags, $id);
      }
      if($caseFolding) {
         foreach($ret as &$n) $n = strtolower($n);
         if($dm) $ret = array_unique($ret);
      }
      return count($ret) <= 1 ? reset($ret) : $ret;
   }

//    function firstChild() {
//       $doc = $this->doc();
//       $q = reset($this->ids);
//       $p = key($this->ids);
//       return new HTML_Node($doc, array($p=>$q));
//    }
//
//    function lastChild() {
//       $doc = $this->doc();
//       $q = end($this->ids);
//       $p = key($this->ids);
//       return new HTML_Node($doc, array($p=>$q));
//    }

// - Helpers ------------------------------------------------

    /// Normalize a CSS selector pseudo-class string.
    /// ( int, string or array(name => value) )
    static function html_normal_pseudoClass($p) {
        if(is_int($p)) return $p;
        $i = (int)$p;
        if((string)$i === $p) return $i;

        static $map = array(
            'lt'       => '<',
            'gt'       => '>',
            'prev'     => '-',
            'next'     => '+',
            'parent'   => '|',
            'children' => '*',
            '*'        => '*'
        );
        $p = explode('(', $p, 2);
        $p[1] = isset($p[1]) ? trim(rtrim($p[1], ')')) : NULL;
        switch($p[0]) {
            case 'first'      :
            case 'first-child': return 0;
            case 'last'       :
            case 'last-child' : return -1;
            case 'eq'         : return (int)$p[1];
            default:
                if(isset($map[$p[0]])) {
                    $p[0] = $map[$p[0]];
                    if(isset($p[1])) $p[1] = (int)$p[1];
                } else {
                // ??? unknown ps
                }
        }
        return array($p[0]=>$p[1]);
    }

    // ------------------------------------------------------------------------
    /*! Parse a selector string into an array structure.
     *
     * tn1#id1 .cl1.cl2:first tn2:5 , tn3.cl3 tn4#id2:eq(-1) > tn5:last-child > tn6:lt(3)
     *  -->
     *   [
     *      [
     *          [{ n: "tn1", i: "id1", c: [],            p: []  }],
     *          [{ n: NULL,  i: NULL,  c: ["cl1","cl2"], p: [0] }],
     *          [{ n: "tn2", i: NULL,  c: [],            p: [5] }]
     *      ]
     *    , [
     *          [{ n: "tn3", i: NULL, c: ["cl3"], p: [] }],
     *          [
     *              { n: "tn4", i: "id2", c: [], p: [-1]   },
     *              { n: "tn5", i: NULL , c: [], p: [-1]   },
     *              { n: "tn6", i: NULL , c: [], p: ["<3"] }
     *          ]
     *      ]
     *   ]
     */
    static function html_selector2struc($sel) {
        $sc = '#.:';
        $n = NULL; $a = array();
        $def = array('n'=>$n, 'i'=>$n, 'c'=>$a, 'p'=>$a);
        $sel = rtrim(trim(preg_replace('/\\s*(>|,)\\s*/', '$1', $sel), " \t\n\r,>"), $sc);
        $sel = explode(',', $sel);
        foreach($sel as &$a) {
           $a = preg_split('|\\s+|', $a);
           foreach($a as &$b) {
              $b = explode('>', $b);
              foreach($b as &$c) {
                 $d = $def;
                 $l = strlen($c);
                 $j = strcspn($c, $sc, 0, $l);
                 if($j) $d['n'] = substr($c, 0, $j);
                 $i = $j;
                 while($i<$l) {
                    $k = $c[$i++];
                    $j = strcspn($c, $sc, $i, $l);
                    if($j) {
                       $e = substr($c, $i, $j);
                       switch($k) {
                          case '.': $d['c'][] = $e; break;
                          case '#': $d['i']   = $e; break;
                          case ':': $d['p'][] = self::html_normal_pseudoClass($e); break;
                       }
                       $i+=$j;
                    }
                 }
                 if(empty($d['c'])) $d['c'] = $n;
                 if(empty($d['p'])) $d['p'] = $n;
                 $c = $d;
              }
           }
        }
        return $sel;
    }

    // ------------------------------------------------------------------------
    static function html_findTagClose($str, $p) {
        if($i = strpos($str, '>', $p)) {
            $p += strcspn($str, $qs="\"'", $p, $i);
            $l = strlen($str);
            while($p < $i) {
                $q = $str[$p];
                ++$p;
                $p += strcspn($str, $q, $p, $l);
                if(++$p > $i) {
                    $i = strpos($str, '>', $p);
                    if(!$i) break;
                }
                $p += strcspn($str, $qs, $p, $i);
            }
        }
        return $i;
    }
    // ------------------------------------------------------------------------
    static function html_parseAttrStr($str, $case_folding = true, $extended = false) {
        static $_attrName_firstLet = NULL;
        if(!$_attrName_firstLet) $_attrName_firstLet = self::str_range('a-zA-Z_');

        $ret = array();
        for($i = strspn($str, " \t\n\r"), $len = strlen($str); $i < $len;) {
           $i += strcspn($str, $_attrName_firstLet, $i);
           if($i>=$len) break;
           $b = $i;
           $i += strcspn($str, " \t\n\r=\"\'", $i);
           $attrName = rtrim(substr($str, $b, $i-$b));
           if($case_folding) $attrName = strtolower($attrName);
           $i += strspn($str, " \t\n\r", $i);
           $attrValue = NULL;
           if($i<$len && $str[$i]=='=') {
             ++$i;
             $i += strspn($str, " \t\n\r", $i);
             if($i < $len) {
               $q = substr($str, $i, 1);
               if($q=='"' || $q=="'") {
                  $b = ++$i;
                  $e = strpos($str, $q, $i);
                  if($e !== false) {
                     $attrValue = substr($str, $b, $e-$b);
                     $i = $e+1;
                  }
                  else {
                     /*??? no closing quote */
                  }
               }
               else {
                  $b = $i;
                  $i += strcspn($str, " \t\n\r\"\'", $i);
                  $attrValue = substr($str, $b, $i-$b);
               }
             }
           }
           if($extended && $attrValue) switch($case_folding ? $attrName : strtolower($attrName)) {
              case 'class':
                $attrValue = preg_split("|\\s+|", trim($attrValue));
                if(count($attrValue) == 1) $attrValue = reset($attrValue);
                else sort($attrValue);
                break;

              case 'style':
                $attrValue = self::parseCSStr($attrValue, $case_folding);
                break;
           }

           $ret[$attrName] = $attrValue;
        }
        return $ret;
    }
    // ------------------------------------------------------------------------
    static function html_attr2str($attr, $quote='"') {
        $sq = htmlspecialchars($quote);
        if($sq == $quote) $sq = false;
        ksort($attr);
        if(isset($attr['class']) && is_array($attr['class'])) { sort($attr['class']); $attr['class'] = implode(' ', $attr['class']); }
        if(isset($attr['style']) && is_array($attr['style'])) $attr['style'] = self::CSSArr2Str($attr['style']);
        $ret = array();
        foreach($attr as $n => $v) {
            $ret[] = $n . '=' . $quote . ($sq ? str_replace($quote, $sq, $v) : $v) . $quote;
        }
        return implode(' ', $ret);
    }
    // ------------------------------------------------------------------------
    static function parseCSStr($str, $case_folding = true) {
      $ret = array();
      $a = explode(';', $str); // ??? what if ; in "" ?
      foreach($a as $v) {
         $v = explode(':', $v, 2);
         $n = trim(reset($v));
         if($case_folding) $n = strtolower($n);
         $ret[$n] = count($v) == 2 ? trim(end($v)) : NULL;
      }
      unset($ret['']);
      return $ret;
    }

    static function CSSArr2Str($css) {
       if(is_array($css)) {
          ksort($css);
          $ret = array();
          foreach($css as $n => $v) $ret[] = $n.':'.$v;
          return implode(';', $ret);
       }
       return $css;
    }
    // ------------------------------------------------------------------------
    static function str_range($comp, $pos=0, $len=NULL) {
        $ret = array();
        $b = strlen($comp);
        if(!isset($len) || $len > $b) $len = $b;
        $b = "\x0";
        while($pos < $len) {
            switch($c = $comp[$pos++]) {
                case '\\': {
                    $b = substr($comp, $pos, 1);
                    $ret[$b] = $pos++;
                } break;

                case '-': {
                    $c_ = ord($c=substr($comp, $pos, 1));
                    $b = ord($b);
                    while($b++ < $c_) $ret[chr($b)] = $pos;
                    while($b-- > $c_) $ret[chr($b)] = $pos;
                } break;

                default: {
                    $ret[$b=$c] = $pos;
                }
            }
        }
        return implode('', array_keys($ret));
    }
    // ------------------------------------------------------------------------
    static function array_select($arr, $keys, $force_null=false) {
        $ret = array();
        is_array($keys) or is_object($keys) or $keys = array($keys);
        foreach($keys as $k)
            if(isset($arr[$k])) $ret[$k] = $arr[$k];
            elseif($force_null) $ret[$k] = NULL;
        return $ret;
    }
    // ------------------------------------------------------------------------
    static function convert_encoding($a, $to, $from=NULL) {
        static $meth = NULL;
        isset($meth) or $meth = function_exists('mb_convert_encoding');
        isset($from) or $from = $meth ? mb_internal_encoding() : iconv_get_encoding('internal_encoding');

        if(is_array($a)) {
            $ret = array();
            foreach($a as $n => $v) {
                $ret[is_string($n)?self::convert_encoding($n,$to,$from):$n] = is_string($v) || is_array($v) || $v instanceof stdClass
                    ? self::convert_encoding($v, $to, $from)
                    : $v;
            }
            return $ret;
        }
        elseif($a instanceof stdClass) {
            $ret = (object)array();
            foreach($a as $n => $v) {
                $ret->{is_string($n)?self::convert_encoding($n,$to,$from):$n} = is_string($v) || is_array($v) || $v instanceof stdClass
                    ? self::convert_encoding($v, $to, $from)
                    : $v;
            }
            return $ret;
        }
        return is_string($a) ? $meth ? mb_convert_encoding($a, $to, $from) : iconv($from, $to, $a) : $a;
    }
    // ------------------------------------------------------------------------
}
// ------------------------------------------------------------------------

class IDOM_Context extends ADOM_Node {
    function __construct($doc=NULL, $el_arr=NULL) {
        if($el_arr instanceof parent) {
           if(!$doc) $doc = $el_arr->doc();
           $el_arr = $el_arr->ids;
        }
        elseif(is_array($el_arr)) ksort($el_arr);
        parent::__construct($doc, $el_arr, true);
    }

    /*! ctx($el) * $this
     * @return ctx
     */
    function intersect($el, $eq=true) {
       if($el instanceof self) { if($el === $this) if($eq) return $this; else $el = array(); else $el = $el->ids; } else
       $el = $this->_ctx_ids($this->_doc_ids($el, true));
       foreach($el as $b => $e) if(!$this->has($b, $eq)) unset($el[$b]);
       return new self($el, $this->doc);
    }
}

// ------------------------------------------------------------------------
/// HTML Parser Class
class CHTML_Parser_Doc extends ADOM_Node {
    static $del_spaces          = false;
    static $case_folding        = true;
    static $autoclose_tags      = false; // 1 - auto-close non-empty tags, 2 - auto-close all tags

    static $_emptyTags          = array('base','meta','link','hr','br','basefont','param','img','area','input','isindex','col');
    static $_specialTags        = array('--'=>'--', '[CDATA['=>']]');
    static $_unparsedTags       = array('style', 'script');
    static $_index_attribs      = array('href', 'src');
    static $_url_attribs        = array('href'=>'href', 'src'=>'src');
    static $_tagID_first_letter = 'a-zA-Z_';
    static $_tagID_letters      = 'a-zA-Z_0-9:\-';
    static $_icharset           = 'UTF-8'; // Internal charset

    protected $html = ''; // html string

    // Indexed data
    protected $tags          ; // id        => nodeName
    protected $attrs         ; // id        => attrId
    protected $attribs       ; // attrId    => attrStr | attrArr
    protected $idx_attr      ; // attrName  => [id=>attrVal]
    protected $tag_idx       ; // nodeNames => [ids]  , [ids] == [id => end]
    protected $attr_idx      ; // attrId    => id | [ids]
    protected $class_idx     ; // class     => aid | [aids=>[ids]]

    var $o = NULL;

    protected $indexed = false; // completely indexed


    // ------------------------------------------------------------------------
    // The magic of properties
    function __get($name) {
        if(array_key_exists($name, $this->_prop)) return $this->_prop[$name];
        switch($name) {
            case 'size':
                return $this->strlen();

            case 'baseURI':
                return $this->baseURI();

            case 'base_url':
                return @$this->_prop['baseURL'];

            case 'location':
            case 'href':
                return $this->location();

            case 'charset':
                if($this->html) {
                    $this->_prop['charset'] =
                    $c = self::detect_charset($this->html);
                    return $c;
                }
            break;
        }
    }

    function __set($name, $value) {
        switch($name) {
            case 'hostURL': return false;
            case 'baseURI':
            case 'base_url':
            case 'baseURL':
                return $this->baseURI($value);

            case 'location':
            case 'href':
                return $this->location($value);
        }

        if(isset($value)) return $this->_prop[$name] = $value;
        $this->__unset($name);
    }
    // ------------------------------------------------------------------------

    function location($href=NULL) {
        if(func_num_args() < 1) {
            return @$this->_prop['location']['href'];
        }
        else {
            if(!isset($this->_prop['baseURI'])) {
                $this->baseURI($href);
            }
            $this->_prop['location']['href'] = $href;
        }
        return ;
    }

    /// get/set baseURI
    function baseURI($href=NULL) {
        if(func_num_args() < 1) {
            $href = @$this->_prop['baseURI'];
        }
        else {
            if($href) {
                $t = self::get_url_base($href, true);
                if(!$t) return false;
                list($bh, $bu) = $t;
            }
            else {
                $bh = $bu = NULL;
            }
            $this->_prop['hostURL'] = $bh;
            $this->_prop['baseURL'] = $bu;
            $this->_prop['baseURI'] = $href;
        }
        return $href;

    }

    function __construct($html, $idx=true) {
        if(!is_string($html)) $html = (string)$html;
        $c = self::detect_charset($html) or $c = NULL;
        if($c) {
            $ic = self::$_icharset;
            if($c != $ic) $html = self::convert_encoding($html, $ic, $c);
        }
        $this->_prop['charset'] = $c;
        if(self::$del_spaces) {
            $html = preg_replace('#(>)?\\s+(<)?#', '$1 $2', $html); // reduce the size
        }
        $this->tags = self::$_ar_;
        $l = strlen($html);
        parent::__construct($this, self::$_ar_);
        $this->html = $html;
        unset($html);

        $this->_prop['baseURI'] =
        $this->_prop['baseURL'] =
        $this->_prop['hostURL'] = NULL;

        if($this->html && $idx) $this->_index_all();
    }

    function __toString() { return $this->html; }

    static function get_url_base($url, $array=false) {
        if($ub = self::get_url_path($url)) {
            $up = $ub;
            $q = strpos($up, '/', strpos($up, '//')+2);
            $ub = substr($up, 0, $q+1);
        }
        return $array && $ub ? array($ub, $up) : $ub;
    }

    static function get_url_path($url) {
        $p = strpos($url, '//');
        if($p === false || $p && !preg_match('|^[a-z]+\:$|', substr($url, 0, $p))) return false;
        $q = strrpos($url, '/');
        if($p+1 < $q) {
            $url = substr($url, 0, $q+1);
        } else {
            $url .= '/';
        }
        return $url;
    }

    function url2abs($url) {
        if(isset($this->_prop['baseURL']) && !preg_match('|^([a-z]{1,20}:)?\/\/|', $url)) {
            if($url[0] == '/') { // abs path
                $bu = $this->_prop['hostURL'];
                $url = substr($url, 1);
            }
            else {
                $bu = $this->_prop['baseURL'];
            }
            $url = $bu . $url;
        }
        return $url;
    }

    /* <meta charset="ISO-8859-2" /> */
    /* <meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-2" /> */
    static function detect_charset($str) {
        $l    = 1024;
        $str  = substr($str, 0, $l);
        $str_ = strtolower($str);
        $p = 0;
        while($p < $l) {
            $p = strpos($str_, '<meta', $p);
            if($p === false) break;
            $p+=5;
            $q = strpos($str_, '>', $p);
            if($q < $p) $q = strlen($str_);
            $a = substr($str, $p, $q-$p);
            $p = $q+2;
            $a = self::html_parseAttrStr($a, true);
            if(!empty($a['charset'])) {
                return strtoupper($a['charset']);
            }
            if(isset($a['http-equiv']) && strtolower($a['http-equiv']) === 'content-type') {
                if(empty($a['content'])) return false;
                $a = explode('charset=', $a['content']);
                return empty($a) || empty($a[1]) ? false : strtoupper(trim($a[1]));
            }
        }
        return false;
    }

    public function strlen() {
        return isset($this->html) ? strlen($this->html) : 0;
    }

    /// This method is for debug only
    function _info() {
        $inf = array();
        $ar = array();
        foreach($this->attribs as $i => $a) $ar[$i] = self::html_attr2str($a);
        $inf['attribs']   = $ar   ;
        $inf['attrs']     = $attrs     ;
        $inf['idx_attr']  = $this->idx_attr  ;
        $inf['tag_idx']   = $this->tag_idx   ;
        $inf['attr_idx']  = $this->attr_idx  ;
        $inf['class_idx'] = $this->class_idx ;

        $lev = array();
        $nm = array();
        $st = array();
        $pb = -1; $pe = PHP_INT_MAX;
        $l = 0;
        foreach($this->ids as $b => $e) {
            if($pb < $b && $b < $pe) {
                $st[]  = array($pb, $pe);
                list($pb, $pe) = array($b, $e);
            }
            else while($pe < $b && $st) {
                list($pb, $pe) = array_pop($st);
            }
            $nm[$b] = $this->tags[$b];
            $lev[$b] = count($st);
        }
        foreach($nm as $b => &$n) {
            $n = str_repeat(' -', $lev[$b]) . ' < ' . $n . ' ' . $this->get_attr_byId($b, NULL, true) . ' >';
        }
        $nm = implode("\n", $nm);
        $inf['struc'] = $nm;
        unset($lev, $st, $nm);
        return $inf;
    }

    /// Index comment tags position in source HTML
    protected function _index_comments_html($o) {
        if(!isset($o->l)) $o->l = strlen($o->h);
        $o->tg = self::$_ar_;
        $i = $o->i;
        while($i < $o->l) {
            $i = strpos($o->h, '<!--', $i);
            if($i === false) break;
            $p = $i;
            $i += 4;
            $i = strpos($o->h, '-->', $i);
            if($i === false) break;
            $i += 3;
            $o->tg[$p] = $i;
        }
        return $o;
    }

    /// index all tags by tagname at ids
    private function _index_tags() {
        $s = $nix = $ix = self::$_ar_;
        $ids = $this->ids;
        foreach($this->tags as $id => $n) {
            if(!isset($ix[$n])) $ix[$n] = array();
            $ix[$n][$id] = $ids[$id];
        }
        foreach($ix as $n => $v) {
            foreach($v as $id => $e) $this->tags[$id] = $n;
            if(isset($nix[$n])) continue;
            $_n = strtolower($n);
            if(!isset($nix[$_n])) $nix[$_n] = $v;
            else {
                foreach($v as $id => $e) $nix[$_n][$id] = $e;
                $s[] = $_n;
            }
        }
        foreach($s as $_n) asort($nix[$_n]);
        return $this->tag_idx = $nix;
    }

   /// make attribute ids (aids) and index all attributes by aid at ids
   private function _index_attribs($attrs) {
      $this->attr_idx = $this->attrs = $aix = $six = $iix = $iax = self::$_ar_;
      $i = 0;
      $ian = self::$_index_attribs;
      foreach($ian as $atn) if(!isset($iax[$atn])) $iax[$atn] = self::$_ar_;
      foreach($attrs as $str => $v) {
         $a = self::html_parseAttrStr($str, true, false);
         unset($attrs[$str]);
         foreach($ian as $atn) {
             if(isset($a[$atn])) { // href attribute has a separate index
                if(is_array($v)) foreach($v as $e) $iax[$atn][$e] = $a[$atn];
                else $iax[$atn][$v] = $a[$atn];
                unset($a[$atn]);
             }
         }
         if(empty($a)) continue;
         $str = self::html_attr2str($a);
         $aid = $i;
         if(isset($six[$str])) {
            $aid = $six[$str];
            if(!is_array($iix[$aid])) $iix[$aid] = array($iix[$aid]);
            if(is_array($v)) foreach($v as $v_) $iix[$aid][] = $v_;
            else $iix[$aid][] = $v;
         } else {
            $six[$str] = $aid;
            $aix[$aid] = $a;
            $iix[$aid] = $v;
            ++$i;
         }
      }
      unset($six, $attrs, $i);
      foreach($aix as $aid => $a) {
         $v = $iix[$aid];
         if(is_array($v)) {
            if(count($v) == 1) $v = reset($v); elseif($v) {
              $u = array();
              foreach($v as $e) {
                 $u[$e] = $this->ids[$e];
                 $this->attrs[$e] = $aid;
              }
              $v = $u; unset($u);
            }
         }
         if(!is_array($v)) $this->attrs[$v] = $aid;
         $this->attr_idx[$aid] = $v; // ids for this attr
      }
      foreach($iax as $atn => $v) if(!$v) unset($iax[$atn]);
      $this->idx_attr = $iax;
      $this->attribs = $aix;

      // debug($this->attribs);
      // debug($this->idx_attr);
      return $this->attr_idx;
   }

   /// index all classes by class-name at aids
   private function _index_classes() {
      $ix = array(); $aix = $this->attr_idx;
      foreach($this->attribs as $aid => &$a) {
         if(!empty($a['class'])) {
           $cl = $a['class'];
           if(!is_array($cl)) $cl = preg_split('|\\s+|',trim($cl));
           foreach($cl as $cl) {
              if(isset($ix[$cl])) {
                if(!is_array($ix[$cl])) $ix[$cl] = array($ix[$cl]=>$this->attr_idx[$ix[$cl]]);
                $ix[$cl][$aid] = $this->attr_idx[$aid];
              } else {
                $ix[$cl] = $aid;
              }
           }
         }
      }
      return $this->class_idx = $ix;
   }

    protected function _index_all() {
        if($this->indexed) return $this->tag_idx;

        $this->o = $o = (object)array();
        $o->h   = $this->html;
        $o->l   = strlen($o->h);
        $o->i   = 0;
        $o->tg  = self::$_ar_;
        $fl     = self::str_range(self::$_tagID_first_letter); // first letter chars
        $tl     = self::str_range(self::$_tagID_letters);      // tag name chars
        $i      = $o->i;
        $st     = array('!'=>1, '?'=>2); // special tags
        $ut     = array_flip(self::$_unparsedTags);
        $utn    = NULL;
        $ct     = '/';
        $stack  = $a = self::$_ar_;
        $this->_index_comments_html($o);

        while($i < $o->l) {
            $i = strpos($o->h, '<', $i);
            if($i === false) break;
            ++$i;
            $b = $i;
            $c = $o->h[$i];

            // if close tags
            if($w = $c === $ct) {
                ++$i;
                $c = $o->h[$i];
            }


            // usual tags
            if(strpos($fl, $c) !== false) {
                ++$i; // posibly second letter of tagName
                $j = strspn($o->h, $tl, $i);
                $n = substr($o->h, $i-1, $j+1);
                $i += $j;
                if($utn) {
                    $n = strtolower($n);
                    if($utn !== $n || !$w) {
                        continue;
                    }
                    $utn = NULL;
                }
                $i = self::html_findTagClose($o->h, $i);
                if($i === false) break;
                $e = $i++;
                // open tag
                if(!$w) {
                    $this->ids[$e] = $e;  // the end of the teg contents (>)
                    $this->tags[$e] = $n;
                    $b += $j+1;
                    $b += strspn($o->h, " \n\r\t", $b);
                    if( $b < $e ) {
                        $at = trim(substr($o->h, $b, $e-$b));
                        if($at) {
                            if(!isset($a[$at])) $a[$at] = $e;
                            elseif(!is_array($a[$at])) $a[$at] = array($a[$at], $e);
                            else $a[$at][] = $e;
                        };
                    }
                    if($o->h[$e-1] != $ct) {
                        $n = strtolower($n);
                        if(isset($ut[$n])) {
                            $utn = $n;
                        }
                        $stack[$n][$b] = $e; // put in stack
                    }
                }
                // close tag
                else {
                    $n = strtolower($n);
                    $s = &$stack[$n];
                    if(empty($s)) ;                             // error - tag not opened, but closed - ???
                    else {
                        $q = end($s);
                        $p = key($s);
                        unset($s[$p]);
                        $this->ids[$q] = $b-1;                  // the end of the teg contents (<)
                    }
                }
            }
            elseif(!$w) {
                // special tags
                if(isset($st[$c])) {
                    $b--;
                    if(isset($o->tg[$b])) {
                        $i = $o->tg[$b];
                        continue;
                    }
                    // ???
                }
                else continue;          // not a tag
                $i = strpos($o->h, '>', $i);
                if($i === false) break;
                $e = $i++;
            };
        }

        foreach($stack as $n => $st) if(empty($st)) unset($stack[$n]);
        // if(self::$autoclose_tags) {
            // foreach($stack as $n => $st) { // ???
            // }
        // } else {
            // foreach($stack as $n => $st) { // ???
            // }
        // }

        $this->_index_tags();
        $this->_index_attribs($a); unset($a);
        $this->_index_classes();

        // debug($stack); // unclosed tags

        $this->o = self::$_nl_;
        $this->indexed = true;
        if(!empty($this->tag_idx['base'])) {
            foreach($this->tag_idx['base'] as $b => $e) {
                if($a = $this->get_attr_byId($b, 'href', false)) {
                    $this->baseURI($a);
                    break;
                }
            }
        }

        return $this->tag_idx;
    }

   // ------------------------------------------------------------------------
    function _get_ctx($ctx) {
         if(!($ctx instanceof parent)) {
            if(is_array($ctx) || is_int($ctx))
               $ctx = new IDOM_Context($this, $ctx, true);
            else $ctx = self::$_fl_;
         }
         return $ctx && count($ctx) ? $ctx : self::$_fl_; // false for error - something is not ok
    }
   // ------------------------------------------------------------------------
    function _find($name, $class=NULL, $attr=NULL, $ctx=NULL, $rec=true) {
      // if(!in_array($name, array('meta', 'head'))) debug(compact('name', 'class', 'attr','ctx', 'rec'));

      $aids = NULL;
      if($class) {
         if($attr)    $aids = $this->get_aids_byClassAttr($class, $attr, true);
         else         $aids = $this->get_aids_byClass($class, true);
         if(!$aids) return self::$_nl_;
      }
      elseif($attr) {
         $aids = $this->get_aids_byAttr($attr, true);
         if(!$aids) return self::$_nl_;
      }

      if(is_string($name) && $name !== '' && $name != '*') {
         $name = strtolower(trim($name));
         if(empty($this->tag_idx[$name])) return self::$_nl_; // no such tag-name
      }
      else $name = NULL;

      if(isset($ctx)) {
         $ctx = $this->_get_ctx($ctx);
         if(!$ctx) throw new Exception(__CLASS__.'->'.__FUNCTION__.': Invalid context!');
      }

      if(isset($aids)) {
         $ni = $this->get_ids_byAid($aids, true, true);
         if($ni && $ctx) $ni = $ctx->_filter($ni);
         if(!$ni) return self::$_nl_;
         if($name) $ni = array_intersect_key($ni, $this->tag_idx[$name]);
      }
      else {
         if($name) {
            $ni = $this->tag_idx[$name];
            if($ni && $ctx) $ni = $ctx->_filter($ni);
         }
         else {
            if($ctx) $ni = $ctx->_sub_ids(false);
            else $ni = $this->ids; // all tags
         }
      }

      return $ni ? $ni : self::$_nl_;
   }

   /*!
    * @return false - no class, 0 - hasn't class, true - has class, [ids.cl]
    */
   function hasClass($id, $cl) {
      if(!is_array($cl)) $cl = preg_split('|\\s+|',trim($cl));
      if(is_array($id)) {
         $ret = self::$_ar_;
         foreach($id as $id => $e) {
            $c = $this->hasClass($id, $cl);
            if($c) $ret[$id] = $e;
            elseif($c === false) return $c;
         }
         return $ret;
      }
      if(!isset($this->$attrs[$id])) return 0;    // $id has no attributes at all (but indexed)
      foreach($cl as $cl) {
        if(!isset($this->class_idx[$cl])) return self::$_fl_; // class doesn't exist
        $cl = $this->class_idx[$cl];
        $aid = $this->attrs[$id];
        if(!(is_array($cl) ? isset($cl[$aid]) : $cl == $aid)) return 0;
      }
      return self::$_tr_;
   }

//    protected
    function filter($ids, $name=NULL, $class=NULL, $attr=NULL, $ctx=NULL) {
      $aids = NULL;
      if($class) {
         if($attr)    $aids = $this->get_aids_byClassAttr($class, $attr, true);
         else         $aids = $this->get_aids_byClass($class, true);
         if(!$aids) return self::$_nl_;
      }
      elseif($attr) {
         $aids = $this->get_aids_byAttr($attr, true);
         if(!$aids) return self::$_nl_;
      }
      unset($class, $attr);
      if($aids) {
         foreach($ids as $b => $e) if(!isset($this->attrs[$b], $aids[$this->attrs[$b]])) unset($ids[$b]);
         if(!$ids) return self::$_nl_;
      }
      unset($aids);

      if(is_string($name) && $name !== '' && $name != '*') {
         $name = strtolower(trim($name));
         if(empty($this->tag_idx[$name])) return self::$_nl_; // no such tag-name
         foreach($ids as $b => $e) if(!isset($this->tag_idx[$name][$b])) unset($ids[$b]);
         if(!$ids) return self::$_nl_;
      }
      unset($name);

      if(isset($ctx)) {
         $ctx = $this->_get_ctx($ctx);
         if(!$ctx) throw new Exception(__CLASS__.'->'.__FUNCTION__.': Invalid context!');
         $ids = $ctx->_filter($ids);
         if(!$ids) return $ids;
      }
      unset($ctx);
      return $ids;
   }
   // ------------------------------------------------------------------------

   /*!
    * @return [aids]
    */
   function get_aids_byAttr($attr, $as_keys=false, $actx=NULL) {
      $aids = self::$_ar_;
      if(isset($actx) && !$actx) return $aids;
      if(is_string($attr)) $attr = self::html_parseAttrStr($attr);
      if($actx)
      foreach($actx as $aid => $a) {
         if(!isset($this->attribs[$aid])) continue;
         $a = $this->attribs[$aid];
         $good = true;
         foreach($attr as $n => $v) if(!isset($a[$n]) || $a[$n] !== $v) { $good = false; break; }
         if($good) $aids[$aid] = $this->attr_idx[$aid];
      }
      else
      foreach($this->attribs as $aid => $a) {
         $good = true;
         foreach($attr as $n => $v) if(!isset($a[$n]) || $a[$n] !== $v) { $good = false; break; }
         if($good) $aids[$aid] = $this->attr_idx[$aid];
      }
      return $as_keys ? $aids : array_keys($aids);
   }

   /*!
    * @return $as_keys ? [aid => id | [ids]] : [aids]
    */
   function get_aids_byClass($cl, $as_keys=false, $actx=NULL) {
      $aids = self::$_ar_;
      if(isset($actx) && !$actx) return $aids;
      if(!is_array($cl)) $cl = preg_split('|\\s+|',trim($cl));
      if(!$cl) $cl = array_keys($this->class_idx); // efectul multimii vide
      foreach($cl as $cl) if(isset($this->class_idx[$cl])) {
         $aid = $this->class_idx[$cl];
         if(!$actx) {
            if(is_array($aid)) foreach($aid as $aid => $cl) $aids[$aid] = $cl;
            else $aids[$aid] = $this->attr_idx[$aid];
         }
         else {
            if(is_array($aid)) foreach($aid as $aid => $cl) if(isset($actx[$aids])) $aids[$aid] = $cl;
            else if(isset($actx[$aids])) $aids[$aid] = $this->attr_idx[$aid];
         }
      }
      else return self::$_ar_; // no such class
      return $as_keys ? $aids : array_keys($aids);
   }

   function get_aids_byClassAttr($cl, $attr, $as_keys=false, $actx=NULL) {
      $aids = $this->get_aids_byClass($cl, true, $actx);
      if(is_string($attr)) $attr = self::html_parseAttrStr($attr);
      if($attr) foreach($aids as $aid => $ix) {
         $a = $this->attribs[$aid];
         $good = count($a) > 1; // has only 'class' attribute
         if($good) foreach($attr as $n => $v) {
            if(!isset($a[$n]) || $a[$n] !== $v) {
                $good = false;
                break;
            }
         }
         if(!$good) unset($aids[$aid]);
      }
      return $as_keys ? $aids : array_keys($aids);
   }

   /*!
    * $has_keys == true  => $aid == [aid=>[ids]]
    * $has_keys == false => $aid == [aids]
    */
   function get_ids_byAid($aid, $sort=true, $has_keys=false) {
        $ret = self::$_ar_;
        if(!$has_keys) $aid = self::array_select($this->attr_idx, $aid);
        foreach($aid as $aid => $aix) {
           if(!is_array($aix)) $aix =array($aix=>$this->ids[$aix]);
           if(empty($ret)) $ret = $aix;
           else foreach($aix as $id => $e) $ret[$id] = $e;
        }
        if($sort && $ret) ksort($ret);
        return $ret;
   }

   function get_ids_byAttr($attr, $sort=true) {
      $ret = self::$_ar_;
      if(is_string($attr)) $attr = self::html_parseAttrStr($attr);
      if(!$attr) return $ret;
      $sat = $ret;
      foreach(self::$_index_attribs as $atn) {
        if(isset($attr[$atn])) {
           if(empty($this->idx_attr[$atn])) return $ret;
           $sat[$atn] = $attr[$atn];
           unset($attr[$atn]);
        }
      }
      if($attr) {
        $aids = $this->get_aids_byAttr($attr, true);
        if(!$aids) return $ret;
        foreach($aids as $aid => $aix) {
           if(!is_array($aix)) $aix = array($aix=>$this->ids[$aix]);
           foreach($aix as $id => $e) {
              if($sat) {
                 $good = true;
                 foreach($sat as $n => $v) {
                     if(!isset($this->idx_attr[$n][$id]) || $this->idx_attr[$n][$id] !== $v) {
                        $good = false;
                        break;
                     }
                 }
                 if($good) $ret[$id] = $e;
              }
              else  $ret[$id] = $e;
           }
        }
      } else { // !$attr && $sat
        $av = reset($sat); $an = key($sat); unset($sat[$an]);
        $aix = $this->idx_attr[$an];
        foreach($aix as $id => $v) {
           if($v !== $av) continue;
           $e = $this->ids[$id];
           if($sat) {
                $good = true;
                foreach($sat as $n => $v) {
                    if(!isset($this->idx_attr[$n][$id]) || $this->idx_attr[$n][$id] !== $v) {
                        $good = false;
                        break;
                    }
                }
                if($good) $ret[$id] = $e;
           }
           else {
                $ret[$id] = $e;
           }
        }
      }
      if($sort) ksort($ret);
      return $ret;
   }

   function get_ids_byClass($cl, $sort=true) {
      $aids = $this->get_aids_byClass($cl, true);
      return $this->get_ids_byAid($aids, $sort, true);
   }

   function get_ids_byClassAttr($cl, $attr, $sort=true) {
      $aids = $this->get_aids_byClassAttr($cl, $attr, true);
      return $this->get_ids_byAid($aids, $sort, true);
   }

   function get_attr_byAid($aid, $to_str=false) {
      if(is_array($aid)) {
         $ret = self::$_ar_;
         foreach($aid as $aid) $ret[$aid] = $this->get_attr_byAid($aid, $to_str);
      } else {
         if(!isset($this->attribs[$aid])) return self::$_fl_;
         $ret = $this->attribs[$aid];
         if($to_str) $ret = self::html_attr2str($ret);
      }
      return $ret;
   }

   function get_attr_byId($id, $attr=NULL, $to_str=false) {
      $ret = self::$_ar_;
      if(is_array($id)) {
         foreach($id as $id => $e) $ret[$id] = $this->get_attr_byId($id, $attr, $to_str);
      }
      else {
         if(!isset($this->ids[$id])) return self::$_fl_;
         $bu = isset($this->_prop['baseURL']);
         if(isset($attr)) {
            if(isset($this->idx_attr[$attr])) $ret = @$this->idx_attr[$attr][$id];
            else $ret = isset($this->attrs[$id], $this->attribs[$ret=$this->attrs[$id]]) ? @$this->attribs[$ret][$attr] : self::$_nl_;
            if($ret && $bu && isset(self::$_url_attribs[$attr])) {
                $ret = $this->url2abs($ret);
            }
         }
         else {
            if(isset($this->attrs[$id])) $ret = $this->attribs[$this->attrs[$id]];
            foreach(self::$_index_attribs as $atn) {
               if(isset($this->idx_attr[$atn][$id])) $ret[$atn] = $this->idx_attr[$atn][$id];
            }

            if(!empty($bu)) {
              foreach(self::$_url_attribs as $n) {
                 if(isset($ret[$n])) $ret[$n] = $this->url2abs($ret[$n]);
              }
            }
            if($to_str) $ret = self::html_attr2str($ret);
         }
      }
      return $ret;
   }
};

// ------------------------------------------------------------------------

// ------------------------------------------------------------------------
/// The Main Class for HTML document
// ------------------------------------------------------------------------
class hQuery extends CHTML_Parser_Doc {

    // ------------------------------------------------------------------------
    // Response headers when using self::fromURL()
    public $headers;

    static public $cache_path;
    static public $cache_expires = 3600;

    // ------------------------------------------------------------------------
    /**
     *  Parse and HTML string.
     *
     *  @return (hQuery)$doc
     */
    static function fromHTML($html, $url=NULL) {
        $index_time = microtime(true);
        $doc = new self($html, false);
        if($url) {
            $doc->location($url);
        }
        $doc->index();
        $index_time = microtime(true) - $index_time;
        $doc->index_time = $index_time * 1000;
        return $doc;
    }

    /**
     *  Read the HTML document from a file.
     *
     *  @return (hQuery)$doc
     */
    static function fromFile($filename, $use_include_path=false, $context=NULL) {
        $read_time = microtime(true);
        $html = file_get_contents($filename, $use_include_path, $context);
        $read_time = microtime(true) - $read_time;
        if($html === false) return $html;
        $doc = self::fromHTML($html, $filename);
        $doc->source_type = 'file';
        $doc->read_time = $read_time * 1000;
        return $doc;
    }

    /**
     *  Fetch the HTML document from remote $url.
     *
     *  @param (string)       $url     - the URL of the document
     *  @param (array)        $headers - request headers
     *  @param (array|string) $body    - body of the request (for POST or PUT)
     *  @param (array)        $options - request options
     *
     *  @return (hQuery)$doc
     */
    static function fromURL($url, $headers=NULL, $body=NULL, $options=NULL) {
        $opt = array(
            'timeout'   => 7,
            'redirects' => 7,
            'close'     => false,
            'decode'    => 'gzip',
            'expires'   => self::$cache_expires,
        );
        $hd = array('Accept-Charset' => 'UTF-8,*');

        if($options) $opt = $options + $opt;
        if($headers) $hd  = $headers + $hd;

        $expires = $opt['expires'];
        unset($opt['expires']);

        if(0 < $expires and $dir = self::$cache_path) {
            ksort($opt);
            $t = realpath($dir) and $dir = $t or mkdir($dir, 0766, true);
            $dir .= DS;
            $cch_id = hash('sha1', $url, true);
            $t = hash('md5', serialize($opt), true);
            $cch_id = bin2hex(substr($cch_id, 0, -strlen($t)) . (substr($cch_id, -strlen($t)) ^ $t));
            $cch_fn = $dir . $cch_id;
            $ext = strtolower(strrchr($url, '.'));
            if(strlen($ext) < 7 && preg_match('/^\\.[a-z0-9]+$/', $ext)) {
                $cch_fn .= $ext;
            }
            $cch_fn .= '.gz';
            $read_time = microtime(true);
            $ret = self::get_cache($cch_fn, $expires, false);
            $read_time = microtime(true) - $read_time;
            if($ret) {
                $source_type = 'cache';
                $html = $ret[0];
                $hdrs = $ret[1]['hdr'];
                $code = $ret[1]['code'];
                $url  = $ret[1]['url'];
                $cch_meta = $ret[1];
            }
        }
        else {
            $ret = NULL;
        }

        if(empty($ret)) {
            $source_type = 'url';
            $read_time = microtime(true);
            $ret = self::http_wr($url, $hd, $body, $opt);
            $read_time = microtime(true) - $read_time;
            $html = $ret->body;
            $code = $ret->code;
            $hdrs = $ret->headers;

            // Catch the redirects
            if($ret->url) $url = $ret->url;

            if(!empty($cch_fn)) {
                $save = self::set_cache($cch_fn, $html, array('hdr' => $hdrs, 'code' => $code, 'url' => $url));
            }
        }
        if($code != 200) {
            return false;
        }

        $doc = self::fromHTML($html, $url);
        if($doc) {
            $doc->headers = $hdrs;
            $doc->source_type = $source_type;
            isset($read_time) and $doc->read_time = $read_time * 1000;
            if(!empty($cch_meta)) $doc->cch_meta = $cch_meta;
        }

        return $doc;
    }

    // ------------------------------------------------------------------------

    function index() { return $this->_index_all(); }

    function find_html($sel, $attr=NULL, $ctx=NULL) {
        $r = $this->find($sel, $attr=NULL, $ctx=NULL);
        $ret = self::$_ar_;
        if($r) foreach($r as $k => $v) $ret[$k] = $v->html();
        return $ret;
    }

    function find_text($sel, $attr=NULL, $ctx=NULL) {
        $r = $this->find($sel, $attr=NULL, $ctx=NULL);
        $ret = self::$_ar_;
        if($r) foreach($r as $k => $v) $ret[$k] = $v->text();
        return $ret;
    }

    function exclude($sel, $attr=NULL) {
        $e = $this->find($sel, $attr, $this);
        if($e) {
            if(empty($this->exc)) $this->exc = $e;
            else {
                foreach($e->ids as $b => $e) $this->exc[$b] = $e;
                ksort($this->exc);
            }
        }
        return $e;
    }

    function find($sel, $attr=NULL, $ctx=NULL) {
        $c = func_num_args();
        for($i=1;$i<$c;$i++) {
            $a = func_get_arg($i);
            if(is_int($a)) $pos = $a;
            elseif(is_array($a))  $attr = array_merge($attr, $a);
            elseif(is_string($a)) $attr = array_merge($attr, self::html_parseAttrStr($a));
            elseif(is_object($a)) {
                if($a instanceof ADOM_Node) $ctx = $a;
                else throw new Exception('Wrong context in ' . __METHOD__);
            }
        }
        if(isset($ctx)) $ctx = $this->_get_ctx($ctx);
        if(!isset($attr)) $attr = array();

        $sel = self::html_selector2struc($sel);

        $ra = NULL;
        // , //
        foreach($sel as $a) {
            $rb = NULL;
            $cx = $ctx;
            //   //
            foreach($a as $b) {
                $rc = NULL;
                if($rb) {
                    $cx = $this->_get_ctx($rb);
                    if(!$cx) ; // ??? error
                }
                // > //
                foreach($b as $c) {
                    $at = $attr;
                    if(isset($c['i'])) $at['id'] = $c['i'];
                    // x of x > y > ...
                    if(!$rc) {
                        $rc = $this->_find($c['n'], $c['c'], $at, $cx);
                    }
                    // y of x > y > ...
                    else {
                        $ch = $this->_children($rc);
                        $rc = $this->filter($ch, $c['n'], $c['c'], $at);
                    }
                    unset($ch);
                    if(!$rc) break;
                    if(isset($c['p'])) {
                        foreach($c['p'] as $p) {
                            if(is_int($p)) {
                                if($p < 0) $p += count($rc);
                                if(count($rc) >= 1 || $p) {
                                    $rc = $p < 0 ? NULL : array_slice($rc, $p, 1, true);
                                }
                            }
                            elseif(is_array($p)) {
                                $ch = reset($p);
                                switch(key($p)) {
                                    case '<': $rc = array_slice($rc, 0, $ch, true);          break;
                                    case '>': $rc = array_slice($rc, $ch, count($rc), true); break;
                                    case '-': $rc = $this->_prev($rc, $ch); break;
                                    case '+': $rc = $this->_next($rc, $ch); break;
                                    case '|': do $rc = $this->_parent($rc);   while($ch-- > 0); break;
                                    case '*': do $rc = $this->_children($rc); while($ch-- > 0); break;
                                }
                            }
                            if(!$rc) break 2;
                        }
                    }
                }
                $rb = $rc;
                if(!$rb) break;
            }
            if($rc) if(!$ra) $ra = $rc; else { foreach($rc as $rb => $rc) $ra[$rb] = $rc; }
        }
        if($ra) {
            ksort($ra);
            return new HTML_Node($this, $ra);
        }
        return NULL;
    }

    // - Helpers ------------------------------------------------

    /// Read data from a cache file
    protected static function get_cache($fn, $expire=false, $meta_only=false) {
        $meta = $cnt = NULL;
        if( $fm = @filemtime($fn) and (!$expire || $fm + $expire > time()) ) {
            $cnt = self::flock_get_contents($fn);
        }
        $t = strlen($cnt);
        if(!empty($cnt)) {
            if($gz = !strncmp($cnt, "\x1F\x8B", 2)) {
                $cnt = function_exists('gzdecode') ? gzdecode($cnt) : NULL;
            }
            if($cnt[0] == '#') {
                $n = (int)substr($cnt, 1, 0x10);
                $l = strlen($n) + 2;
                if($n) {
                    $meta = substr($cnt, $l, $n);
                    if($meta !== '') $meta = unserialize($meta);
                }
                if($meta_only) $cnt = '';
                else {
                    $l += $n;
                    if($cnt[$l] == "\n") {
                        $cnt = substr($cnt, ++$l);
                        if($cnt !== '') $cnt = unserialize($cnt);
                    }
                    else {
                        $cnt = substr($cnt, $l);
                    }
                }
            }
            else {
                if($meta_only) $cnt = '';
            }
        }
        return $cnt || $meta ? array($cnt, $meta) : false;
    }

    /// Save data to a cache file
    protected static function set_cache($fn, $cnt, $meta=NULL, $gzip=true) {
        if($cnt === false) return !file_exists($fn) || unlink($fn);
        $n = 0;
        if(isset($meta)) {
           $meta = serialize($meta);
           $n += strlen($meta);
        }
        $meta = '#'.$n . "\n" . $meta;
        if(!is_string($cnt) || $cnt[0] == "\n") { $cnt = "\n" . serialize($cnt); ++$n; }
        if($n) $cnt = $meta . $cnt;
        unset($meta);
        @mkdir(dirname($fn), 0777, true);
        if($gzip) {
            $gl = is_int($gzip) ? $gzip : 1024;
            strlen($cnt) > $gl && function_exists('gzdecode') and
            $cnt = gzencode($cnt);
        }
        return self::flock_put_contents($fn, $cnt);
    }

    /**
     * Lock with retries
     *
     * @param (resource)$fp         - Open file pointer
     * @param (int)     $lock       - Lock type
     * @param (int)     $timeout_ms - Timeout to wait for unlock in miliseconds
     *
     * @return true on success, false on fail
     *
     * @author Dumitru Uzun
     *
     */
    static function do_flock($fp, $lock, $timeout_ms=384) {
        $l = flock($fp, $lock);
        if(!$l && ($lock & LOCK_UN) != LOCK_UN && ($lock & LOCK_NB) != LOCK_NB ) {
            $st = microtime(true);
            $m = min( 1e3, $timeout_ms*1e3);
            $n = min(64e3, $timeout_ms*1e3);
            if($m == $n) $m = ($n >> 1) + 1;
            $timeout_ms = (float)$timeout_ms / 1000;
            // If lock not obtained sleep for 0 - 64 milliseconds, to avoid collision and CPU load
            do {
                usleep($t = rand($m, $n));
                $l = flock($fp, $lock);
            } while ( !$l && (microtime(true)-$st) < $timeout_ms );
        }
        return $l;
    }

    static function flock_put_contents($fn, $cnt, $block=false) {
       // return file_put_contents($fn, $cnt, $block & FILE_APPEND);
       $ret = false;
       if( $f = fopen($fn, 'c+') ) {
           $app = $block & FILE_APPEND and $block ^= $app;
           if( $block ? self::do_flock($f, LOCK_EX) : flock($f, LOCK_EX | LOCK_NB) ) {
              if(is_array($cnt) || is_object($cnt)) $cnt = serialize($cnt);
              if($app) fseek($f, 0, SEEK_END);
              if(false !== ($ret = fwrite($f, $cnt))) ftruncate($f, ftell($f));
              flock($f, LOCK_UN);
           }
           fclose($f);
       }
       return $ret;
    }

    static function flock_get_contents($fn, $block=false) {
       // return file_get_contents($fn);
       $ret = false;
       if( $f = fopen($fn, 'r') ) {
           if( flock($f, LOCK_SH | ($block ? 0 : LOCK_NB)) ) {
              $s = 1 << 14 ;
              do $ret .= $r = fread($f, $s); while($r !== false && !feof($f));
              if($ret == NULL && $r === false) $ret = $r;
              // filesize result is cached
              flock($f, LOCK_UN);
           }
           fclose($f);
       }
       return $ret;
    }
    // ------------------------------------------------------------------------
    static function is_url_path($path) {
        return preg_match('/^[a-zA-Z]+\:\/\//', $path);
    }

    static function is_abs_path($path) {
        $ds = array('\\'=>1,'/'=>2);
        if( isset($ds[substr($path, 0, 1)]) ||
            substr($path, 1, 1) == ':' && isset($ds[substr($path, 2, 1)])
        ) {
            return true;
        }
        if(($l=strpos($path, '://')) && $l < 32) return $l;
        return false;
    }

    static function abs_url($url, $base) {
        if(!self::is_url_path($url)) {
            $t = parse_url($base);
            if(substr($url, 0, 2) == '//') {
                $url = (empty($t['scheme']) ? 'http' : $t['scheme']) . ':' . $url;
            }
            else {
                $base = $t['scheme'] . '://' . $t['host'] . (empty($t['port'])?'':':'.$t['port']);
                if(!empty($t['path'])) {
                    $s = dirname($t['path']);
                    if($s && $s !== '.' && $s !== '/' && substr($url, 0, 1) !== '/') {
                        $base .= '/' . ltrim($s, '/');
                    }
                }
                $url = rtrim($base, '/') . '/' . ltrim($url, '/');
            }
        }
        else {
            $p = strpos($url, ':');
            if( substr($url, $p+3, 1) === '/' && in_array(substr($url, 0, $p), array('http','https')) ) {
                $url = substr($url, 0, $p+3) . ltrim(substr($url, $p+3), '/');
            }
        }
        return $url;
    }
    // ------------------------------------------------------------------------
    /**
     * Executes a HTTP write-read session.
     *
     * @param (string)$host - IP/HOST address or URL
     * @param (array) $head - list off HTTP headers to be sent along with the request to $host
     * @param (mixed) $body - data to be sent as the contents of the request. If is array or object, a http query is built.
     * @param (array) $options - list of option as key-value:
     *                              timeout - connection timeout in seconds
     *                              host    - goes in headers, overrides $host (ex. $host == '127.0.0.1', $options['host'] == 'www.example.com')
     *                              scheme  - ssl, tls, udp, ...
     *                              close   - whether to close connection o not
     *
     * @return (array)response: [contents, headers, http-status-code, http-status-message]
     *
     * @author Dumitru Uzun
     *
     */
    static function http_wr($host, $head=NULL, $body=NULL, $options=NULL) {
        $ret = (object)(array());
        empty($options) and $options = array();
        if($p = strpos($host, '://') and $p < 7) {
            $ret->url = $host;
            $p = parse_url($host);
            if(!$p) throw new Exception('Wrong host specified'); // error
            $host = $p['host'];
            $path = @$p['path'];
            if(isset($p['query'])) $path .= '?' . $p['query'];
            if(isset($p['port'])) $port = $p['port'];
            unset($p['path'], $p['query']);
            $options += $p;
        }
        else {
            $p = explode('/', $host, 2); list($host, $path) = $p;
            $p = explode(':', $host, 2); list($host, $port) = $p;
        }
        if(strncmp($path, '/', 1)) $path = '/' . $path;
        // isset($path) or $path = '/';

        if(!isset($port)) {
            if(isset($options['port'])) $port = $options['port']; else
            switch($options['scheme']) {
                case 'tls'  :
                case 'ssl'  :
                case 'https': $port = 443; break;
                case 'ftp'  : $port = 21; break;
                case 'sftp' : $port = 22; break;
                case 'http' :
                default     : $port = 80;
            }
        }

        $ret->host = $host;
        $_h = array(
            'host'   => isset($options['host']) ? $options['host'] : $host,
            'accept' => 'text/html,application/xhtml+xml,application/xml;q =0.9,*/*;q=0.8',
        );
        if(!empty($options['scheme'])) {
            switch($p['scheme']) {
                case 'http':
                case 'ftp':
                break;
                case 'https':
                    $host = 'tls://' . $host;
                break;
                default:
                    $host = $options['scheme'] . '://' . $host;
            }
        }

        $boundary = "\r\n\r\n";
        $blen = strlen($boundary);
        if($body) {
           if(is_array($body) || is_object($body)) {
              $body = http_build_query($body);
              $_h['content-type'] = 'application/x-www-form-urlencoded';
           }
           $body = (string)$body;
           $_h['content-length'] = strlen($body);
           $body .= $boundary;
           empty($options['method']) and $options['method'] = 'POST';
        }
        else $body = NULL;

        $meth = @$options['method'] and $meth = strtoupper($meth) or $meth = 'GET';

        if($head) {
           if(!is_array($head)) $head = explode("\r\n", $head);
           foreach($head as $i => $v) {
              if(is_int($i)) {
                 $v = explode(':', $v, 2);
                 if(count($v) != 2) continue; // Invalid header
                 list($i, $v) = $v;
              }
              $i = strtolower(strtr($i, ' _', '--'));
              $_h[$i] = trim($v);
           }
        }

        if(@$options['decode'] == 'gzip') {
            if(function_exists('gzdecode')) {
                $_h['accept-encoding'] = 'gzip';
            }
            else {
                $options['decode'] = NULL;
            }
        }

        if(!isset($options['close']) || @$options['close']) {
            $_h['connection'] = 'close';
        }
        else {
            $_h['connection'] = 'keep-alive';
        }

        $prot = empty($options['protocol']) ? 'HTTP/1.1' : $options['protocol'];

        $head = array("$meth $path $prot");
        foreach($_h as $i => $v) {
            $i = explode('-', $i);
            foreach($i as &$j) $j = ucfirst($j);
            $i = implode('-', $i);
            $head[] = $i . ': ' . $v;
        }
        $rqst = implode("\r\n", $head) . $boundary . $body;
        $head = $body = NULL;

        $timeout = isset($options['timeout']) ? $options['timeout'] : @ini_get("default_socket_timeout");

        $ret->options = $options;

       // ------------------- Connection and data transfer -------------------
       $errno  =
       $errstr =
       $rsps   = '';
       $h = $_rh = NULL;
       $fs = @fsockopen($host, $port, $errno, $errstr, $timeout);
       if(!$fs) throw new Exception('unable to create socket "'.$host.':'.$port.'"'.$errstr, $errno);
       if(!fwrite($fs, $rqst)) {
          throw new Exception("unable to write");
       }
       else {
          $l = $blen - 1;
          // read headers
          while($open = !feof($fs) && ($p = @fgets($fs, 1024))) {
             if($p == "\r\n") break;
             $rsps .= $p;
          }

          if($rsps) {
               $h = explode("\r\n", rtrim($rsps));
               list($rprot, $rcode, $rmsg) = explode(' ', array_shift($h), 3);
               foreach($h as $v) {
                  $v = explode(':', $v, 2);
                  $_rh[strtoupper(strtr($v[0], '- ', '__'))] = isset($v[1]) ? trim($v[1]) : NULL;
               }
               $rsps = NULL;
               switch($rcode) {
                  case 301:
                  case 302:
                  case 303:
                     if( @$options['redirects'] > 0 && $loc = @$_rh['LOCATION'] ) {
                        $loc = self::abs_url($loc, (empty($options['scheme'])?'':$options['scheme'].'//').$host.':'.$port.(empty($options['path'])?'':$options['path']));
                        unset($_h['host'], $options['host'], $options['port'], $options['scheme'], $options['method']);
                        --$options['redirects'];
                        // ??? could save cookies for redirect
                        return self::http_wr($loc, $_h, NULL, $options);
                     }
                  break;
               }
               // Detect body length
               if(@!$open || $rcode < 200 || $rcode == 204 || $rcode == 304 || $meth == 'HEAD') {
                  $te = 1;
               }
               elseif(isset($_rh['TRANSFER_ENCODING']) && strtolower($_rh['TRANSFER_ENCODING']) === 'chunked') {
                  $te = 3;
               }
               elseif(isset($_rh['CONTENT_LENGTH'])) {
                  $bl = (int)$_rh['CONTENT_LENGTH'];
                  $te = 2;
               }
               switch($te) {
                  case 1:
                     break;
                  case 2:
                     while($bl > 0 and $open &= !feof($fs) && ($p = @fread($fs, $bl))) {
                        $rsps .= $p;
                        $bl -= strlen($p);
                     }
                     break;
                  case 3:
                     while($open &= !feof($fs) && ($p = @fgets($fs, 1024))) {
                        $cs = reset(explode(';', rtrim($p)));
                        $bl = hexdec($cs);
                        if(!$bl) break; // empty chunk
                         while($bl > 0 and $open &= !feof($fs) && ($p = @fread($fs, $bl))) {
                            $rsps .= $p;
                            $bl -= strlen($p);
                         }
                         @fgets($fs, 3); // \r\n
                     }
                     if($open &= !feof($fs) && ($p = @fgets($fs, 1024))) {
                        if($p = rtrim($p)) {
                            // ??? Trailer Header
                            $v = explode(':', $p, 2);
                            $_rh[strtoupper(strtr($v[0], '- ', '__'))] = isset($v[1]) ? trim($v[1]) : NULL;
                            @fgets($fs, 3); // \r\n
                        }
                     }
                     break;
                  default:
                         while($open &= !feof($fs) && ($p = @fread($fs, 1024))) { // ???
                            $rsps .= $p;
                         }
                     break;
               }

               if($rsps != '' && @$options['decode'] == 'gzip' && @$_rh['CONTENT_ENCODING'] == 'gzip' && function_exists('gzdecode')) {
                  $r = gzdecode($rsps);
                  if($r !== false) {
                     unset($_rh['CONTENT_ENCODING']);
                     $rsps = $r;
                  }
               }
               $ret->code    = $rcode;
               $ret->msg     = $rmsg;
               $ret->headers = isset($_rh) ? $_rh : NULL;
               $ret->body    = $rsps;
               $ret->method  = $meth;
               // $ret->host    = $host;
               $ret->port    = $port;
               $ret->path    = $path;
               $ret->request = $rqst;

               return $ret;

               // Old return:
                      //     contents  headers  status-code  status-message
               // return array( $rsps,    @$_rh,   $rcode,      $rmsg,           $host, $port, $path, $rqst  );
          }
       }
       fclose($fs);

       return false; // no response
    }
    // ------------------------------------------------------------------------

};

// ------------------------------------------------------------------------
class HTML_Node extends ADOM_Node {
    // ------------------------------------------------------------------------
    // Iterator
    protected $_ich = NULL; // Iterator Cache
    // ------------------------------------------------------------------------
    function toArray($cch=true) {
        if($cch && isset($this->_ich) && count($this->ids) === count($this->_ich)) return $this->_ich;
        $ret = array();
        if($cch) {
            foreach($this->ids as $b => $e) {
                $ret[$b] = isset($this->_ich[$b]) ? $this->_ich[$b] : ( $this->_ich[$b] = new self($this->doc, array($b=>$e)) );
            }
        }
        else {
            foreach($this->ids as $b => $e) {
                $ret[$b] = new self($this->doc, array($b=>$e));
            }
        }
        return $ret;
    }

    // ------------------------------------------------------------------------
    function __get($name) {
        switch($name) {
            case 'html'     : return $this->html();
            case 'outerHtml': return $this->outerHtml();
            case 'text'     : return $this->text();
            case 'attr'     : return $this->attr();
            case 'val'      : return $this->val();
            case 'nodeName' : return $this->nodeName(false);
            case 'parent'   : return $this->parent();
            case 'children' : return $this->children();
            case 'className': $name = 'class'; break;
        }
        // return parent::__get($name);

        if(array_key_exists($name, $this->_prop)) return $this->_prop[$name];
        switch($name) {
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
    public function offsetSet($offset, $value) {
        if(is_null($offset)) {
            // $this->_data[] = $value; // ???
        }
        if(is_int($offset)) {
            $i = array_slice($this->ids, $offset, 1, true);
            // ???
        }
        else {
            $this->__set($offset, $value);
        }
    }

    public function offsetGet($offset) {
        if(is_int($offset)) {
            $i = array_slice($this->ids, $offset, 1, true);
            return $i ? new self($this->doc, $i) : NULL;
        }
        return $this->__get($offset);
    }

    public function offsetExists($offset) {
        if(is_int($offset)) {
            return 0 <= $offset && $offset < count($this->ids);
        }
        return $this->__isset($offset);
    }

    public function offsetUnset($offset) {
        if(is_int($offset)) {
            $i = array_slice($this->ids, $offset, 1, true);
            if($i) {
                $i = key($i);
                unset($this->ids[$i], $this->_ich[$i]);
            }
        }
        else {
            unset($this->_prop[$offset]);
        }
    }
    // ------------------------------------------------------------------------

    function val() {
        switch(strtoupper($this->nodeName(false))) {
            case 'TEXTAREA':
                return $this->html();
            case 'INPUT':
                switch(strtoupper($this->attr('type'))) {
                case 'CHECKBOX': return $this->attr('checked') !== false;
                default:         return $this->attr('value');
                }
            return $this->html();
            case 'SELECT': // ???

            default: return false;
        }
    }

    // Override current for iterations
    function current() {
        $k = key($this->ids);
        if($k === NULL) return false;
        if(count($this->ids) == 1) return $this;
        if(!isset($this->_ich[$k])) $this->_ich[$k] = new self($this->doc, array($k=>$this->ids[$k]));
        return $this->_ich[$k];
    }

    // Get the node at $idx position in the set, using cache
    function get($idx) {
        $i = array_slice($this->ids, $idx, 1, true);
        if(!$i) return NULL;

        // Try read cache first
        $k = key($i);
        if(isset($this->_ich[$k])) return $this->_ich[$k];

        // Create wraper instance for $i
        $o = new self($this->doc, $i);

        // Save to cache
        $this->_ich[$k] = $o;

        return $o;
    }

    // Get the node at $idx position in the set, no cache, each call creates new instance
    function eq($idx) {
        $i = array_slice($this->ids, $idx, 1, true) or
        $i = array();
        // Create wraper instance for $i
        $o = new self($this->doc, $i);
        return $o;
    }

    function slice($idx, $len=NULL) {
        $c = $this->count();
        if($idx < $c) $p += $c;
        if($idx < $c) $ids = array(); else
        if(isset($len)) {
            if($idx == 0 && $len == $c) {
                return $this; // ???
                $ids = $this->ids;
            }
            $ids = array_slice($this->ids, $idx, $len, true);
        } else {
            if($idx == 0) {
                return $this; // ???
                $ids = $this->ids;
            }
            $ids = array_slice($this->ids, $idx, $this->count(), true);
        }
        $o = new self($this->doc, $ids);
        $o->_ich = &$this->_ich; // share iterator cache for iteration
        return $o;
    }

    function parent() {
        $p = $this->_parent();
        return $p ? new self($this->doc, $p) : NULL;
    }

    function children() {
        $p = $this->_children();
        return $p ? new self($this->doc, $p) : NULL;
    }

};

// ------------------------------------------------------------------------

?>