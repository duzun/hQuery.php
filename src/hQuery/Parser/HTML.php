<?php
namespace duzun\hQuery\Parser;

use duzun\hQuery\Parser;

// ------------------------------------------------------------------------
/**
 *  CSS selector parser class.
 *
 *  @internal
 *  @license MIT
 */
class HTML extends Parser
{
    /**
     * @var array
     */
    public $tg = array();

    /**
     * @var array
     */
    public static $_unparsedTags = array('style', 'script');

    /**
     * 1 - auto-close non-empty tags,
     * 2 - auto-close all tags
     * @var boolean
     */
    public static $autoclose_tags = false;

    // ------------------------------------------------------------------------
    /**
     * @param  string $html
     * @return array  [$ids, $tags, $attr]
     */
    public static function exec($html)
    {
        $p = new self($html);
        return $p->parse();
    }

    /**
     * @return array [$ids, $tags, $attr]
     */
    public function parse()
    {
        // Index comments first, so than we can safely ignore them
        $this->_index_comments();

        $firstLetterChars = self::$nameStartRange;  // first letter chars
        $tagLettersChars  = self::$nameRange . ':'; // tag name chars

        $specialTags  = array('!' => 1, '?' => 2); // special tags
        $unparsedTags = array_flip(self::$_unparsedTags);

        $utn  = null; // current unparsed tag name
        $html = $this->s;
        $i    = $this->i;
        $l    = $this->l;

        $stack = $tags = $ids = $attr = array();

        while ($i < $l) {
            $i = strpos($html, '<', $i);
            if (false === $i) {
                // no more tags in $html
                break;
            }

            ++$i;
            $b = $i;
            $c = $html[$i];

            // if close tag
            if ($isCloseTag = '/' === $c) {
                ++$i;
                $c = $html[$i];
            }

            // regular tag
            if (false !== strpos($firstLetterChars, $c)) {
                ++$i; // possibly second letter of tagName
                $j = strspn($html, $tagLettersChars, $i);
                $n = substr($html, $i - 1, $j + 1);
                $i += $j;
                if ($utn) {
                    $n = strtolower($n);
                    if ($utn !== $n || !$isCloseTag) {
                        continue;
                    }
                    $utn = null;
                }
                $i = self::_findTagClose($html, $i);
                if (false === $i) {
                    // this tag never closes - malformed HTML?
                    break;
                }

                $e = $i++;
                // open tag
                if (!$isCloseTag) {
                    $ids[$e]  = $e; // the end of tag attributs (>) and start of tag contents
                    $tags[$e] = $n;
                    $b += $j + 1;
                    $b += strspn($html, " \n\r\t", $b);
                    if ($b < $e) {
                        $at = trim(substr($html, $b, $e - $b));
                        if ($at) {
                            if (!isset($attr[$at])) {
                                $attr[$at] = $e;
                            } elseif (!is_array($attr[$at])) {
                                $attr[$at] = array($attr[$at], $e);
                            } else {
                                $attr[$at][] = $e;
                            }

                        }
                    }
                    // Not an empty tag
                    if ('/' != $html[$e - 1]) {
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
                        $ids[$q] = $b - 1; // the end of the tag contents (<)
                    }
                }
            } elseif (!$isCloseTag) {
                // special tags
                if (isset($specialTags[$c])) {
                    --$b;
                    if (isset($this->tg[$b])) {
                        $i = $this->tg[$b];
                        continue;
                    }
                    // ???
                } else {
                    continue;
                }
                // not a tag
                $i = strpos($html, '>', $i);
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

        return array($ids, $tags, $attr);

        // if(self::$autoclose_tags) {
        // foreach($stack as $n => $st) { // ???
        // }
        // } else {
        // foreach($stack as $n => $st) { // ???
        // }
        // }
    }

    /**
     * Index comment tags position in source HTML
     */
    protected function _index_comments()
    {
        $s = $this->s;
        $l = $this->l;
        $i = $this->i;

        while ($i < $l) {
            $i = strpos($s, '<!--', $i);
            if (false === $i) {
                break;
            }

            $p = $i;
            $i += 4;
            $i = strpos($s, '-->', $i);
            if (false === $i) {
                $i = $l;
            } else {
                $i += 3;
            }

            $this->tg[$p] = $i;
        }
    }

    // ------------------------------------------------------------------------
    /**
     * @param  string $str
     * @param  int    $p         position
     * @return int    position
     */
    protected static function _findTagClose($str, $p)
    {
        $l = strlen($str);
        while ($i = $p < $l ? strpos($str, '>', $p) : $l) {
            $e = $p; // save pos
            $p += strcspn($str, '"\'', $p, $i);

            // If closest quote is after '>', return pos of '>'
            if ($p >= $i) {
                return $i;
            }

                           // If there is any quote before '>', make sure '>' is outside an attribute string
            $q = $str[$p]; // " | ' ?

            // next char after the quote
            ++$p;

            // is there a '=' before first quote?
            $e += strcspn($str, '=', $e, $p);

            // is this the attr_name (like in "attr_name"="attr_value") ?
            if ($e >= $p) {
                // Attribute name should not have '>'
                $p += strcspn($str, '>' . $q, $p, $l);
                // but if it has '>', it is the tag closing char
                if ('>' == $str[$p]) {
                    return $p;
                }

            }
            // else, its attr_value
            else {
                $p += strcspn($str, $q, $p, $l);
            }

            ++$p; // next char after the quote
        }
        return $i;
    }

    // ------------------------------------------------------------------------
    /**
     * @param  string  $str
     * @param  boolean $case_folding If TRUE, use lowercase of attribute names
     * @param  boolean $extended     If TRUE, class attribute with more than one class becomes array and style attribute is also parsed
     * @return array
     */
    public static function parseAttrStr($str, $case_folding = true, $extended = false)
    {
        isset(self::$nameStartRange) or self::_init_class();
        $_attrName_firstLet = self::$nameStartRange;

        $ret = array();
        for ($i = strspn($str, " \t\n\r"), $len = strlen($str); $i < $len;) {
            $i += strcspn($str, $_attrName_firstLet, $i);
            if ($i >= $len) {
                break;
            }

            $b = $i;
            $i += strcspn($str, " \t\n\r=\"\'", $i);
            $attrName = rtrim(substr($str, $b, $i - $b));
            if ($case_folding) {
                $attrName = strtolower($attrName);
            }

            $i += strspn($str, " \t\n\r", $i);
            $attrValue = null;
            if ($i < $len && '=' == $str[$i]) {
                ++$i;
                $i += strspn($str, " \t\n\r", $i);
                if ($i < $len) {
                    $q = substr($str, $i, 1);
                    if ('"' == $q || "'" == $q) {
                        $b = ++$i;
                        $e = strpos($str, $q, $i);
                        if (false !== $e) {
                            $attrValue = substr($str, $b, $e - $b);
                            $i         = $e + 1;
                        } else {
                            /*??? no closing quote */
                        }
                    } else {
                        $b = $i;
                        $i += strcspn($str, " \t\n\r\"\'", $i);
                        $attrValue = substr($str, $b, $i - $b);
                    }
                }
            }
            if ($extended && $attrValue) {
                switch ($case_folding ? $attrName : strtolower($attrName)) {
                    case 'class':
                        $attrValue = preg_split('|\\s+|', trim($attrValue));
                        if (count($attrValue) == 1) {
                            $attrValue = reset($attrValue);
                        } else {
                            sort($attrValue);
                        }

                        break;

                    case 'style':
                        $attrValue = self::parseCssStr($attrValue, $case_folding);
                        break;
                }
            }

            $ret[$attrName] = $attrValue;
        }
        return $ret;
    }

    // ------------------------------------------------------------------------
    /**
     * @param $attr
     * @param $quote
     */
    public static function attr2str($attr, $quote = '"')
    {
        $sq = htmlspecialchars($quote);
        if ($sq == $quote) {
            $sq = false;
        }

        ksort($attr);
        if (isset($attr['class']) && is_array($attr['class'])) {
            sort($attr['class']);
            $attr['class'] = implode(' ', $attr['class']);
        }
        if (isset($attr['style']) && is_array($attr['style'])) {
            $attr['style'] = self::css2str($attr['style']);
        }
        $ret = array();
        foreach ($attr as $n => $v) {
            $ret[] = $n . '=' . $quote . ($sq ? str_replace($quote, $sq, $v) : $v) . $quote;
        }
        return implode(' ', $ret);
    }

    // ------------------------------------------------------------------------
    /**
     * @param  $str
     * @param  $case_folding
     * @return array
     */
    public static function parseCssStr($str, $case_folding = true)
    {
        $ret = array();
        $a   = explode(';', $str); // ??? what if ; in "" ?
        foreach ($a as $v) {
            $v = explode(':', $v, 2);
            $n = trim(reset($v));
            if ($case_folding) {
                $n = strtolower($n);
            }

            $ret[$n] = count($v) == 2 ? trim(end($v)) : null;
        }
        unset($ret['']);
        return $ret;
    }

    // ------------------------------------------------------------------------
    /**
     * @param  $css
     * @return string
     */
    public static function css2str($css)
    {
        if (is_array($css)) {
            ksort($css);
            $ret = array();
            foreach ($css as $n => $v) {
                $ret[] = $n . ':' . $v;
            }

            return implode(';', $ret);
        }
        return $css;
    }
}
