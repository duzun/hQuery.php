<?php
namespace duzun\hQuery;

// ------------------------------------------------------------------------
class_exists('duzun\\hQuery\\Node', false) or require_once __DIR__ . DIRECTORY_SEPARATOR . 'Node.php';

// ------------------------------------------------------------------------
/**
 *  HTML Parser Class
 *
 *  @internal
 */
class HTML_Parser extends Node
{
    /**
     * @var boolean
     */
    public static $del_spaces = false;

    /**
     * @var boolean
     */
    public static $case_folding = true;

    /**
     * 1 - auto-close non-empty tags,
     * 2 - auto-close all tags
     * @var boolean
     */
    public static $autoclose_tags = false;

    /**
     * @var array
     */
    public static $_emptyTags = ['base', 'meta', 'link', 'hr', 'br', 'basefont', 'param', 'img', 'area', 'input', 'isindex', 'col'];

    /**
     * @var array
     */
    public static $_specialTags = ['--' => '--', '[CDATA[' => ']]'];

    /**
     * @var array
     */
    public static $_unparsedTags = ['style', 'script'];

    /**
     * @var array
     */
    public static $_index_attribs = ['href', 'src'];

    /**
     * @var array
     */
    public static $_url_attribs = ['href' => 'href', 'src' => 'src'];

    /**
     * @var string
     */
    protected static $_tagID_first_letter = 'a-zA-Z_';

    /**
     * @var string
     */
    protected static $_tagID_letters = 'a-zA-Z_0-9:\-';

    /**
     * @var string
     */
    protected static $_icharset = 'UTF-8'; // Internal charset

    /**
     * @var string
     */
    protected $html = ''; // html string

    // Indexed data:

    /**
     * @var array [id => nodeName]
     */
    protected $tags;

    /**
     * @var array [id => attrId]
     */
    protected $attrs;

    /**
     * @var array [attrId => attrStr | attrArr]
     */
    protected $attribs;

    /**
     * @var array [attrName => [id=>attrVal]]
     */
    protected $idx_attr;

    /**
     * @var array [nodeNames => [ids]], where [ids] == [id => end]
     */
    protected $tag_idx;

    /**
     * @var array [attrId => id | [ids]]
     */
    protected $attr_idx;

    /**
     * @var array [class => aid | [aids=>[ids]]]
     */
    protected $class_idx;

    /**
     * @var stdClass
     */
    protected $o = null;

    /**
     * completely indexed?
     * @var boolean
     */
    protected $indexed = false;

    // ------------------------------------------------------------------------
    // The magic of properties
    /**
     * @param  string  $name
     * @return mixed
     */
    public function __get($name)
    {
        if ($this->_prop && array_key_exists($name, $this->_prop)) {
            return $this->_prop[$name];
        }

        switch ($name) {
            case 'size':
                return $this->strlen();

            case 'baseURI':
                return $this->baseURI();

            case 'base_url':
                return @$this->_prop['baseURL'];

            case 'href':
                return $this->location();

            case 'charset':
                if ($this->html) {
                    $this->_prop['charset'] =
                    $c                      = self::detect_charset($this->html);
                    return $c;
                }
                break;
        }
    }

    /**
     * @param  string  $name
     * @param  mixed   $value
     * @return mixed
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'hostURL':return false;
            case 'baseURI':
            case 'base_url':
            case 'baseURL':
                return $this->baseURI($value);

            case 'location':
            case 'href':
                return $this->location($value);
        }

        if (isset($value)) {
            return $this->_prop[$name] = $value;
        }

        $this->__unset($name);
    }

    // ------------------------------------------------------------------------

    /**
     * @param  string $href
     * @return null
     */
    public function location($href = null)
    {
        if (func_num_args() < 1) {
            return @$this->_prop['location']['href'];
        } else {
            if (!isset($this->_prop['baseURI'])) {
                $this->baseURI($href);
            }
            $this->_prop['location']['href'] = $href;
        }
        return;
    }

    /**
     * get/set baseURI
     *
     * @param  string   $href
     * @return string
     */
    public function baseURI($href = null)
    {
        if (func_num_args() < 1) {
            $href = @$this->_prop['baseURI'];
        } else {
            if ($href) {
                $t = self::get_url_base($href, true);
                if (!$t) {
                    return false;
                }

                list($bh, $bu) = $t;
            } else {
                $bh = $bu = null;
            }
            $this->_prop['hostURL'] = $bh;
            $this->_prop['baseURL'] = $bu;
            $this->_prop['baseURI'] = $href;
        }

        return $href;
    }

    /**
     * @param string  $html
     * @param boolean $idx
     */
    public function __construct($html, $idx = true)
    {
        if (!is_string($html)) {
            $html = (string) $html;
        }

        $c = self::detect_charset($html) or $c = null;
        if ($c) {
            $ic = self::$_icharset;
            if ($c != $ic) {
                $html = self::convert_encoding($html, $ic, $c);
            }

        }
        $this->_prop['charset'] = $c;
        if (self::$del_spaces) {
            $html = preg_replace('#(>)?\\s+(<)?#', '$1 $2', $html); // reduce the size
        }
        $this->tags = self::$_ar_;
        $l          = strlen($html);
        parent::__construct($this, self::$_ar_);
        $this->html = $html;
        unset($html);

        $this->_prop['baseURI'] =
        $this->_prop['baseURL'] =
        $this->_prop['hostURL'] = null;

        if ($this->html && $idx) {
            $this->_index_all();
        }

    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->html;
    }

    // ------------------------------------------------------------------------
    /**
     * @param  string  $url
     * @param  boolean $array
     * @return mixed
     */
    public static function get_url_base($url, $array = false)
    {
        if ($ub = self::get_url_path($url)) {
            $up = $ub;
            $q  = strpos($up, '/', strpos($up, '//') + 2);
            $ub = substr($up, 0, $q + 1);
        }
        return $array && $ub ? [$ub, $up] : $ub;
    }

    /**
     * @param  string   $url
     * @return string
     */
    public static function get_url_path($url)
    {
        $p = strpos($url, '//');
        if (false === $p || $p && !preg_match('|^[a-z]+\:$|', substr($url, 0, $p))) {
            return false;
        }

        $q = strrpos($url, '/');
        if ($p + 1 < $q) {
            $url = substr($url, 0, $q + 1);
        } else {
            $url .= '/';
        }

        return $url;
    }

    /**
     * @param  string   $url
     * @return string
     */
    public function url2abs($url)
    {
        if (isset($this->_prop['baseURL'])) {
            return self::abs_url($url, $this->_prop['baseURL']);
        }

        // if(isset($this->_prop['baseURL']) && !preg_match('|^([a-z]{1,20}:)?\/\/|', $url)) {
        //     if($url[0] == '/') { // abs path
        //         $bu = $this->_prop['hostURL'];
        //         $url = substr($url, 1);
        //     }
        //     else {
        //         $bu = $this->_prop['baseURL'];
        //     }
        //     $url = $bu . $url;
        // }
        return $url;
    }

    // ------------------------------------------------------------------------
    /**
     * Check whether $path is a valid url.
     *
     * @param  string $path - a path to check
     * @return bool   TRUE if $path is a valid URL, FALSE otherwise
     */
    public static function is_url_path($path)
    {
        return preg_match('/^[a-zA-Z]+\:\/\//', $path);
    }

    /**
     * Check whether $path is an absolute path.
     *
     * @param  string $path - a path to check
     * @return bool   TRUE if $path is an absolute path, FALSE otherwise
     */
    public static function is_abs_path($path)
    {
        $ds = ['\\' => 1, '/' => 2];
        if (isset($ds[substr($path, 0, 1)]) ||
            substr($path, 1, 1) == ':' && isset($ds[substr($path, 2, 1)])
        ) {
            return true;
        }
        if (($l = strpos($path, '://')) && $l < 32) {
            return $l;
        }

        return false;
    }

    /**
     * Given a $url (relative or absolute) and a $base url, returns absolute url for $url.
     *
     * @param  string $url     - relative or absolute URL
     * @param  string $base    - Base URL for $url
     * @return string absolute URL for $url
     */
    public static function abs_url($url, $base)
    {
        if (!self::is_url_path($url)) {
            $t = is_array($base) ? $base : parse_url($base);
            if (strncmp($url, '//', 2) == 0) {
                if (!empty($t['scheme'])) {
                    $url = $t['scheme'] . ':' . $url;
                }
            } else {
                $base = (empty($t['scheme']) ? '//' : $t['scheme'] . '://') .
                    $t['host'] . (empty($t['port']) ? '' : ':' . $t['port']);
                if (!empty($t['path'])) {
                    $s = dirname($t['path'] . 'f');
                    if (DIRECTORY_SEPARATOR != '/') {
                        $s = strtr($s, DIRECTORY_SEPARATOR, '/');
                    }
                    if ($s && '.' !== $s && '/' !== $s && substr($url, 0, 1) !== '/') {
                        $base .= '/' . ltrim($s, '/');
                    }
                }
                $url = rtrim($base, '/') . '/' . ltrim($url, '/');
            }
        } else {
            $p = strpos($url, ':');
            if (substr($url, $p + 3, 1) === '/' && in_array(substr($url, 0, $p), ['http', 'https'])) {
                $url = substr($url, 0, $p + 3) . ltrim(substr($url, $p + 3), '/');
            }
        }
        return $url;
    }

    // ------------------------------------------------------------------------
    /* <meta charset="utf-8" /> */
    /* <meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-2" /> */
    /**
     * @param string $str
     */
    public static function detect_charset($str)
    {
        $l    = 1024;
        $str  = substr($str, 0, $l);
        $str_ = strtolower($str);
        $p    = 0;
        while ($p < $l) {
            $p = strpos($str_, '<meta', $p);
            if (false === $p) {
                break;
            }

            $p += 5;
            $q = strpos($str_, '>', $p);
            if ($q < $p) {
                $q = strlen($str_);
            }

            $a = substr($str, $p, $q - $p);
            $p = $q + 2;
            $a = self::html_parseAttrStr($a, true);
            if (!empty($a['charset'])) {
                return strtoupper($a['charset']);
            }
            if (isset($a['http-equiv']) && strtolower($a['http-equiv']) === 'content-type') {
                if (empty($a['content'])) {
                    return false;
                }

                $a = explode('charset=', $a['content']);
                return empty($a) || empty($a[1]) ? false : strtoupper(trim($a[1]));
            }
        }
        return false;
    }

    /**
     * @return int Length of document's HTML
     */
    public function strlen()
    {
        return isset($this->html) ? strlen($this->html) : 0;
    }

    /**
     * @param int $start
     * @param int $length
     */
    public function substr($start, $length = null)
    {
        return isset($this->html)
            ? substr($this->html, $start, isset($length) ? $length : strlen($this->html))
            : self::$_fl_;

    }

    /**
     * This method is for debugging only ( __debugInfo()??? )
     * @return array
     */
    public function _info()
    {
        $inf = [];
        $ar  = [];
        foreach ($this->attribs as $i => $a) {
            $ar[$i] = self::html_attr2str($a);
        }

        $inf['attribs']   = $ar;
        $inf['attrs']     = $this->attrs;
        $inf['idx_attr']  = $this->idx_attr;
        $inf['tag_idx']   = $this->tag_idx;
        $inf['attr_idx']  = $this->attr_idx;
        $inf['class_idx'] = $this->class_idx;

        $lev = [];
        $nm  = [];
        $st  = [];
        $pb  = -1;
        $pe  = PHP_INT_MAX;
        $l   = 0;
        foreach ($this->ids as $b => $e) {
            if ($pb < $b && $b < $pe) {
                $st[]          = [$pb, $pe];
                list($pb, $pe) = [$b, $e];
            } else {
                while ($pe < $b && $st) {
                    list($pb, $pe) = array_pop($st);
                }
            }

            $nm[$b]  = $this->tags[$b];
            $lev[$b] = count($st);
        }
        foreach ($nm as $b => &$n) {
            $n = str_repeat(' -', $lev[$b]) . ' < ' . $n . ' ' . $this->get_attr_byId($b, null, true) . ' >';
        }
        $nm           = implode("\n", $nm);
        $inf['struc'] = $nm;
        unset($lev, $st, $nm);
        return $inf;
    }

    /**
     * Index comment tags position in source HTML
     *
     * @param  stdClass $o
     * @return stdClass $o
     */
    protected function _index_comments_html($o)
    {
        if (!isset($o->l)) {
            $o->l = strlen($o->h);
        }

        $o->tg = self::$_ar_;
        $i     = $o->i;
        while ($i < $o->l) {
            $i = strpos($o->h, '<!--', $i);
            if (false === $i) {
                break;
            }

            $p = $i;
            $i += 4;
            $i = strpos($o->h, '-->', $i);
            if (false === $i) {
                $i = $o->l;
            } else {
                $i += 3;
            }

            $o->tg[$p] = $i;
        }
        return $o;
    }

    /**
     * index all tags by tagname at ids
     *
     * @return array
     */
    private function _index_tags()
    {
        $s   = $nix   = $ix   = self::$_ar_;
        $ids = $this->ids;
        foreach ($this->tags as $id => $n) {
            // if(!isset($ix[$n])) $ix[$n] = array();
            $ix[$n][$id] = $ids[$id];
        }
        foreach ($ix as $n => $v) {
            foreach ($v as $id => $e) {
                $this->tags[$id] = $n;
            }

            if (isset($nix[$n])) {
                continue;
            }

            $_n = strtolower($n);
            if (isset($nix[$_n])) {
                foreach ($v as $id => $e) {
                    $nix[$_n][$id] = $e;
                }

                $s[] = $_n;
            } else {
                $nix[$_n] = $v;
            }
        }
        foreach ($s as $_n) {
            asort($nix[$_n]);
        }

        return $this->tag_idx = $nix;
    }

    /**
     * make attribute ids (aids) and index all attributes by aid at ids
     *
     * @param  $attrs
     * @return array
     */
    private function _index_attribs($attrs)
    {
        $this->attr_idx = $this->attrs = $aix = $six = $iix = $iax = self::$_ar_;
        $i              = 0;
        $ian            = self::$_index_attribs;
        foreach ($ian as $atn) {
            if (!isset($iax[$atn])) {
                $iax[$atn] = self::$_ar_;
            }
        }

        foreach ($attrs as $str => $v) {
            $a = self::html_parseAttrStr($str, true, false);
            unset($attrs[$str]); // free mem
            foreach ($ian as $atn) {
                if (isset($a[$atn])) {
                    // href attribute has a separate index
                    if (is_array($v)) {
                        foreach ($v as $e) {
                            $iax[$atn][$e] = $a[$atn];
                        }

                    } else {
                        $iax[$atn][$v] = $a[$atn];
                    }
                    unset($a[$atn]);
                }
            }
            if (empty($a)) {
                continue;
            }

            $str = self::html_attr2str($a);
            $aid = $i;
            if (isset($six[$str])) {
                $aid = $six[$str];
                if (!is_array($iix[$aid])) {
                    $iix[$aid] = [$iix[$aid]];
                }

                if (is_array($v)) {
                    foreach ($v as $v_) {
                        $iix[$aid][] = $v_;
                    }
                } else {
                    $iix[$aid][] = $v;
                }

            } else {
                $six[$str] = $aid;
                $aix[$aid] = $a;
                $iix[$aid] = $v;
                ++$i;
            }
        }
        unset($six, $attrs, $i); // free mem
        foreach ($aix as $aid => $a) {
            $v = $iix[$aid];
            if (is_array($v)) {
                if (count($v) == 1) {
                    $v = reset($v);
                } elseif ($v) {
                    $u = [];
                    foreach ($v as $e) {
                        $u[$e]           = $this->ids[$e];
                        $this->attrs[$e] = $aid;
                    }
                    $v = $u;unset($u);
                }
            }
            if (!is_array($v)) {
                $this->attrs[$v] = $aid;
            }

            $this->attr_idx[$aid] = $v; // ids for this attr
        }
        foreach ($iax as $atn => $v) {
            if (!$v) {
                unset($iax[$atn]);
            }
        }

        $this->idx_attr = $iax;
        $this->attribs  = $aix;

        return $this->attr_idx;
    }

    /**
     * index all classes by class-name at aids
     *
     * @return array
     */
    private function _index_classes()
    {
        $ix  = [];
        $aix = $this->attr_idx;
        foreach ($this->attribs as $aid => &$a) {
            if (!empty($a['class'])) {
                $cl = $a['class'];
                if (!is_array($cl)) {
                    $cl = preg_split('|\\s+|', trim($cl));
                }
                foreach ($cl as $cl) {
                    if (isset($ix[$cl])) {
                        if (!is_array($ix[$cl])) {
                            $ix[$cl] = [$ix[$cl] => $this->attr_idx[$ix[$cl]]];
                        }

                        $ix[$cl][$aid] = $this->attr_idx[$aid];
                    } else {
                        $ix[$cl] = $aid;
                    }
                }
            }
        }

        return $this->class_idx = $ix;
    }

    /**
     * @return array
     */
    protected function _index_all()
    {
        if ($this->indexed) {
            return $this->tag_idx;
        }

        $this->indexed = true;

        // Parser state object
        $this->o = $o = new \stdClass();
        $o->h    = $this->html;
        $o->l    = strlen($o->h);
        $o->i    = 0;
        $o->tg   = self::$_ar_;
        $this->_index_comments_html($o);

        $firstLetterChars = self::str_range(self::$_tagID_first_letter); // first letter chars
        $tagLettersChars  = self::str_range(self::$_tagID_letters);      // tag name chars
        $specialTags      = ['!' => 1, '?' => 2];                        // special tags
        $unparsedTags     = array_flip(self::$_unparsedTags);

        $utn   = null; // current unparsed tag name
        $i     = $o->i;
        $stack = $a = self::$_ar_;

        while ($i < $o->l) {
            $i = strpos($o->h, '<', $i);
            if (false === $i) {
                break;
            }

            ++$i;
            $b = $i;
            $c = $o->h[$i];

            // if close tags
            if ($isCloseTag = '/' === $c) {
                ++$i;
                $c = $o->h[$i];
            }

            // usual tags
            if (false !== strpos($firstLetterChars, $c)) {
                ++$i; // posibly second letter of tagName
                $j = strspn($o->h, $tagLettersChars, $i);
                $n = substr($o->h, $i - 1, $j + 1);
                $i += $j;
                if ($utn) {
                    $n = strtolower($n);
                    if ($utn !== $n || !$isCloseTag) {
                        continue;
                    }
                    $utn = null;
                }
                $i = self::html_findTagClose($o->h, $i);
                if (false === $i) {
                    break;
                }

                $e = $i++;
                // open tag
                if (!$isCloseTag) {
                    $this->ids[$e]  = $e; // the end of the tag contents (>)
                    $this->tags[$e] = $n;
                    $b += $j + 1;
                    $b += strspn($o->h, " \n\r\t", $b);
                    if ($b < $e) {
                        $at = trim(substr($o->h, $b, $e - $b));
                        if ($at) {
                            if (!isset($a[$at])) {
                                $a[$at] = $e;
                            } elseif (!is_array($a[$at])) {
                                $a[$at] = [$a[$at], $e];
                            } else {
                                $a[$at][] = $e;
                            }

                        }
                    }
                    if ('/' != $o->h[$e - 1]) {
                        $n = strtolower($n);
                        if (isset($unparsedTags[$n])) {
                            $utn = $n;
                        }
                        $stack[$n][$b] = $e; // put in stack
                    }
                }
                // close tag
                else {
                    $n = strtolower($n);
                    $s = &$stack[$n];
                    if (empty($s)); // error - tag not opened, but closed - ???
                    else{
                        $q = end($s);
                        $p = key($s);
                        unset($s[$p], $s);
                        $this->ids[$q] = $b - 1; // the end of the tag contents (<)
                    }
                }
            } elseif (!$isCloseTag) {
                // special tags
                if (isset($specialTags[$c])) {
                    --$b;
                    if (isset($o->tg[$b])) {
                        $i = $o->tg[$b];
                        continue;
                    }
                    // ???
                } else {
                    continue;
                }
                // not a tag
                $i = strpos($o->h, '>', $i);
                if (false === $i) {
                    break;
                }

                $e = $i++;
            }
        }

        foreach ($stack as $n => $st) {
            if (empty($st)) {
                unset($stack[$n]);
            }
        }

        // if(self::$autoclose_tags) {
        // foreach($stack as $n => $st) { // ???
        // }
        // } else {
        // foreach($stack as $n => $st) { // ???
        // }
        // }

        $this->_index_tags();
        $this->_index_attribs($a);unset($a);
        $this->_index_classes();

        // debug($stack); // unclosed tags

        $this->o = self::$_nl_; // Free mem

        // Read <base href="..." /> tag
        if (!empty($this->tag_idx['base'])) {
            foreach ($this->tag_idx['base'] as $b => $e) {
                if ($a = $this->get_attr_byId($b, 'href', false)) {
                    $this->baseURI($a);
                    break;
                }
            }
        }

        return $this->tag_idx;
    }

    // ------------------------------------------------------------------------
    /**
     * @param  mixed                  $ctx
     * @return duzun\hQuery\Context
     */
    protected function _get_ctx($ctx)
    {
        if (!($ctx instanceof parent)) {
            if (is_array($ctx) || is_int($ctx)) {
                $ctx = new Context($this, $ctx, true);
            } else {
                $ctx = self::$_fl_;
            }
        }
        return $ctx && count($ctx) ? $ctx : self::$_fl_; // false for error - something is not ok
    }

    // ------------------------------------------------------------------------
    /**
     * @param  string       $name
     * @param  array|string $class
     * @param  array|string $attr
     * @param  mixed        $ctx
     * @param  boolean      $rec
     * @return array
     */
    protected function _find($name, $class = null, $attr = null, $ctx = null, $rec = true)
    {
        // if(!in_array($name, array('meta', 'head'))) debug(compact('name', 'class', 'attr','ctx', 'rec'));

        $aids = null;
        if ($class) {
            if ($attr) {
                $aids = $this->get_aids_byClassAttr($class, $attr, true);
            } else {
                $aids = $this->get_aids_byClass($class, true);
            }

            if (!$aids) {
                return self::$_nl_;
            }

        } elseif ($attr) {
            $aids = $this->get_aids_byAttr($attr, true);
            if (!$aids) {
                return self::$_nl_;
            }

        }

        if (is_string($name) && '' !== $name && '*' != $name) {
            $name = strtolower(trim($name));
            if (empty($this->tag_idx[$name])) {
                return self::$_nl_;
            }
            // no such tag-name
        } else {
            $name = null;
        }

        if (isset($ctx)) {
            $ctx = $this->_get_ctx($ctx);
            if (!$ctx) {
                throw new \Exception(__CLASS__ . '->' . __FUNCTION__ . ': Invalid context!');
            }

        }

        if (isset($aids)) {
            $ni = $this->get_ids_byAid($aids, true, true);
            if ($ni && $ctx) {
                $ni = $ctx->_filter_contains($ni);
            }

            if (!$ni) {
                return self::$_nl_;
            }

            if ($name) {
                $ni = array_intersect_key($ni, $this->tag_idx[$name]);
            }

        } else {
            if ($name) {
                $ni = $this->tag_idx[$name];
                if ($ni && $ctx) {
                    $ni = $ctx->_filter_contains($ni);
                }

            } else {
                if ($ctx) {
                    $ni = $ctx->_sub_ids(false);
                } else {
                    $ni = $this->ids;
                }
                // all tags
            }
        }

        return $ni ? $ni : self::$_nl_;
    }

    /**
     * Checks whether $this element/collection has a(ll) class(es).
     *
     * @param  int|array|string|Node $id - The context to check
     * @param  string|array          $cl - class(es) to check
     * @return false                 - no class, - doesn't have any class, true - has class, [id => true]
     */
    public function hasClass($id, $cl)
    {
        if (!is_array($cl)) {
            $cl = preg_split('|\\s+|', trim($cl));
        }

        if (is_string($id) && !is_numeric($id)) {
            $id = $this->find($id);
        }
        if ($id instanceof Node) {
            $exc = $id->exc;
            $id  = $id->ids;
            if (!empty($exc)) {
                $id = array_diff_key($id, $exc);
            }
        }
        if (is_array($id)) {
            $ret = self::$_ar_;
            foreach ($id as $id => $e) {
                $c = $this->hasClass($id, $cl);
                if ($c) {
                    $ret[$id] = $e;
                } elseif (false === $c) {
                    return $c;
                }

            }
            return $ret;
        }
        if (!isset($this->attrs[$id])) {
            return 0;
        }
        // $id has no attributes at all (but indexed)
        foreach ($cl as $cl) {
            if (!isset($this->class_idx[$cl])) {
                return self::$_fl_;
            }
            // class doesn't exist
            $cl  = $this->class_idx[$cl];
            $aid = $this->attrs[$id];
            if (is_array($cl) ? !isset($cl[$aid]) : $cl != $aid) {
                return 0;
            }

        }
        return self::$_tr_;
    }

    /**
     *
     * @param  array        $ids
     * @param  string       $name
     * @param  array|string $class
     * @param  array|string $attr
     * @param  mixed        $ctx
     * @return array
     */
    protected function _filter($ids, $name = null, $class = null, $attr = null, $ctx = null)
    {
        $aids = null;
        if ($class) {
            if ($attr) {
                $aids = $this->get_aids_byClassAttr($class, $attr, true);
            } else {
                $aids = $this->get_aids_byClass($class, true);
            }

            if (!$aids) {
                return self::$_nl_;
            }

        } elseif ($attr) {
            $aids = $this->get_aids_byAttr($attr, true);
            if (!$aids) {
                return self::$_nl_;
            }

        }
        unset($class, $attr);
        if ($aids) {
            foreach ($ids as $b => $e) {
                if (!isset($this->attrs[$b], $aids[$this->attrs[$b]])) {
                    unset($ids[$b]);
                }
            }

            if (!$ids) {
                return self::$_nl_;
            }

        }
        unset($aids);

        if (is_string($name) && '' !== $name && '*' != $name) {
            $name = strtolower(trim($name));
            if (empty($this->tag_idx[$name])) {
                return self::$_nl_;
            }
            // no such tag-name
            foreach ($ids as $b => $e) {
                if (!isset($this->tag_idx[$name][$b])) {
                    unset($ids[$b]);
                }
            }

            if (!$ids) {
                return self::$_nl_;
            }

        }
        unset($name);

        if (isset($ctx)) {
            $ctx = $this->_get_ctx($ctx);
            if (!$ctx) {
                throw new \Exception(__CLASS__ . '->' . __FUNCTION__ . ': Invalid context!');
            }

            $ids = $ctx->_filter_contains($ids);
            if (!$ids) {
                return $ids;
            }

        }
        unset($ctx);
        return $ids;
    }

    // ------------------------------------------------------------------------

    /**
     * @return array [aids]
     */
    protected function get_aids_byAttr($attr, $as_keys = false, $actx = null)
    {
        $aids = self::$_ar_;
        if (isset($actx) && !$actx) {
            return $aids;
        }

        if (is_string($attr)) {
            $attr = self::html_parseAttrStr($attr);
        }

        if ($actx) {
            foreach ($actx as $aid => $a) {
                if (!isset($this->attribs[$aid])) {
                    continue;
                }

                $a    = $this->attribs[$aid];
                $good = true;
                foreach ($attr as $n => $v) {
                    if (!isset($a[$n]) || $a[$n] !== $v) {
                        $good = false;
                        break;}
                }

                if ($good) {
                    $aids[$aid] = $this->attr_idx[$aid];
                }

            }
        } else {
            foreach ($this->attribs as $aid => $a) {
                $good = true;
                foreach ($attr as $n => $v) {
                    if (!isset($a[$n]) || $a[$n] !== $v) {
                        $good = false;
                        break;}
                }

                if ($good) {
                    $aids[$aid] = $this->attr_idx[$aid];
                }

            }
        }

        return $as_keys ? $aids : array_keys($aids);
    }

    /**
     * @param  array|string $cl
     * @param  boolean      $as_keys
     * @param  array        $actx
     *
     * @return array $as_keys ? [aid => id | [ids]] : [aids]
     */
    protected function get_aids_byClass($cl, $as_keys = false, $actx = null)
    {
        $aids = self::$_ar_;
        if (isset($actx) && !$actx) {
            return $aids;
        }

        if (!is_array($cl)) {
            $cl = preg_split('|\\s+|', trim($cl));
        }

        if (!$cl) {
            $cl = array_keys($this->class_idx);
        }
        // the empty set effect
        foreach ($cl as $cl) {
            if (isset($this->class_idx[$cl])) {
                $aid = $this->class_idx[$cl];
                if (!$actx) {
                    if (is_array($aid)) {
                        foreach ($aid as $aid => $cl) {
                            $aids[$aid] = $cl;
                        }
                    } else {
                        $aids[$aid] = $this->attr_idx[$aid];
                    }

                } else {
                    if (is_array($aid)) {
                        foreach ($aid as $aid => $cl) {
                            if (isset($actx[$aids])) {
                                $aids[$aid] = $cl;
                            } elseif (isset($actx[$aids])) {
                                $aids[$aid] = $this->attr_idx[$aid];
                            }
                        }
                    }

                }
            } else {
                return self::$_ar_;
            }
        }

        // no such class
        return $as_keys ? $aids : array_keys($aids);
    }

    /**
     * @param  array|string $cl
     * @param  array|string $attr
     * @param  boolean      $as_keys
     * @param  array        $actx
     * @return array
     */
    protected function get_aids_byClassAttr($cl, $attr, $as_keys = false, $actx = null)
    {
        $aids = $this->get_aids_byClass($cl, true, $actx);
        if (is_string($attr)) {
            $attr = self::html_parseAttrStr($attr);
        }

        if ($attr) {
            foreach ($aids as $aid => $ix) {
                $a    = $this->attribs[$aid];
                $good = count($a) > 1; // has only 'class' attribute
                if ($good) {
                    foreach ($attr as $n => $v) {
                        if (!isset($a[$n]) || $a[$n] !== $v) {
                            $good = false;
                            break;
                        }
                    }
                }

                if (!$good) {
                    unset($aids[$aid]);
                }

            }
        }

        return $as_keys ? $aids : array_keys($aids);
    }

    /**
     * $has_keys == true  => $aid == [aid=>[ids]]
     * $has_keys == false => $aid == [aids]
     *
     * @return array
     */
    protected function get_ids_byAid($aid, $sort = true, $has_keys = false)
    {
        $ret = self::$_ar_;
        if (!$has_keys) {
            $aid = self::array_select($this->attr_idx, $aid);
        }

        foreach ($aid as $aid => $aix) {
            if (!is_array($aix)) {
                $aix = [$aix => $this->ids[$aix]];
            }

            if (empty($ret)) {
                $ret = $aix;
            } else {
                foreach ($aix as $id => $e) {
                    $ret[$id] = $e;
                }
            }

        }
        if ($sort && $ret) {
            ksort($ret);
        }

        return $ret;
    }

    /**
     * @param  array|string $attr
     * @param  boolean      $sort
     * @return array
     */
    protected function get_ids_byAttr($attr, $sort = true)
    {
        $ret = self::$_ar_;
        if (is_string($attr)) {
            $attr = self::html_parseAttrStr($attr);
        }

        if (!$attr) {
            return $ret;
        }

        $sat = $ret;
        foreach (self::$_index_attribs as $atn) {
            if (isset($attr[$atn])) {
                if (empty($this->idx_attr[$atn])) {
                    return $ret;
                }

                $sat[$atn] = $attr[$atn];
                unset($attr[$atn]);
            }
        }
        if ($attr) {
            $aids = $this->get_aids_byAttr($attr, true);
            if (!$aids) {
                return $ret;
            }

            foreach ($aids as $aid => $aix) {
                if (!is_array($aix)) {
                    $aix = [$aix => $this->ids[$aix]];
                }

                foreach ($aix as $id => $e) {
                    if ($sat) {
                        $good = true;
                        foreach ($sat as $n => $v) {
                            if (!isset($this->idx_attr[$n][$id]) || $this->idx_attr[$n][$id] !== $v) {
                                $good = false;
                                break;
                            }
                        }
                        if ($good) {
                            $ret[$id] = $e;
                        }

                    } else {
                        $ret[$id] = $e;
                    }

                }
            }
        } else {
            // !$attr && $sat
            $av  = reset($sat);
            $an  = key($sat);unset($sat[$an]);
            $aix = $this->idx_attr[$an];
            foreach ($aix as $id => $v) {
                if ($v !== $av) {
                    continue;
                }

                $e = $this->ids[$id];
                if ($sat) {
                    $good = true;
                    foreach ($sat as $n => $v) {
                        if (!isset($this->idx_attr[$n][$id]) || $this->idx_attr[$n][$id] !== $v) {
                            $good = false;
                            break;
                        }
                    }
                    if ($good) {
                        $ret[$id] = $e;
                    }

                } else {
                    $ret[$id] = $e;
                }
            }
        }
        if ($sort) {
            ksort($ret);
        }

        return $ret;
    }

    /**
     * @param  array|string $cl
     * @param  boolean      $sort
     * @return array
     */
    protected function get_ids_byClass($cl, $sort = true)
    {
        $aids = $this->get_aids_byClass($cl, true);
        return $this->get_ids_byAid($aids, $sort, true);
    }

    /**
     * @param  array|string $cl
     * @param  array|string $attr
     * @param  boolean      $sort
     * @return array
     */
    protected function get_ids_byClassAttr($cl, $attr, $sort = true)
    {
        $aids = $this->get_aids_byClassAttr($cl, $attr, true);
        return $this->get_ids_byAid($aids, $sort, true);
    }

    /**
     * @param  array|string $aid
     * @param  boolean      $to_str
     * @return array
     */
    protected function get_attr_byAid($aid, $to_str = false)
    {
        if (is_array($aid)) {
            $ret = self::$_ar_;
            foreach ($aid as $aid) {
                $ret[$aid] = $this->get_attr_byAid($aid, $to_str);
            }

        } else {
            if (!isset($this->attribs[$aid])) {
                return self::$_fl_;
            }

            $ret = $this->attribs[$aid];
            if ($to_str) {
                $ret = self::html_attr2str($ret);
            }

        }
        return $ret;
    }

    /**
     * @param  array|int      $id
     * @param  array|string   $attr
     * @param  boolean        $to_str
     * @return array|string
     */
    protected function get_attr_byId($id, $attr = null, $to_str = false)
    {
        $ret = self::$_ar_;
        if (is_array($id)) {
            foreach ($id as $id => $e) {
                $ret[$id] = $this->get_attr_byId($id, $attr, $to_str);
            }

        } else {
            if (!isset($this->ids[$id])) {
                return self::$_fl_;
            }

            $bu = isset($this->_prop['baseURL']);
            if (isset($attr)) {
                if (isset($this->idx_attr[$attr])) {
                    $ret = @$this->idx_attr[$attr][$id];
                } else {
                    $ret = isset($this->attrs[$id], $this->attribs[$ret = $this->attrs[$id]]) ? @$this->attribs[$ret][$attr] : self::$_nl_;
                }

                if ($ret && $bu && isset(self::$_url_attribs[$attr])) {
                    $ret = $this->url2abs($ret);
                }
            } else {
                if (isset($this->attrs[$id])) {
                    $ret = $this->attribs[$this->attrs[$id]];
                }

                foreach (self::$_index_attribs as $atn) {
                    if (isset($this->idx_attr[$atn][$id])) {
                        $ret[$atn] = $this->idx_attr[$atn][$id];
                    }

                }

                if (!empty($bu)) {
                    foreach (self::$_url_attribs as $n) {
                        if (isset($ret[$n])) {
                            $ret[$n] = $this->url2abs($ret[$n]);
                        }

                    }
                }
                if ($to_str) {
                    $ret = self::html_attr2str($ret);
                }

            }
        }
        return $ret;
    }
}

// ------------------------------------------------------------------------
// PSR-0 alias
class_exists('hQuery_HTML_Parser', false) or class_alias('duzun\\hQuery\\HTML_Parser', 'hQuery_HTML_Parser', false);
