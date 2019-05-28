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
class Selector extends Parser
{
    // ------------------------------------------------------------------------
    /**
     * @param  string  $sel
     * @return array
     */
    public static function exec($sel)
    {
        $p = new self(trim($sel));
        return $p->parse();
    }

    /**
     * @var string
     */
    public static $combinatorsRange = '>+~';

    /**
     * @var array
     */
    protected static $pseudoMap = array(
        'lt'          => '<',
        'gt'          => '>',
        'prev'        => '-',
        'next'        => '+',
        'parent'      => '|',
        'children'    => '*',
        '*'           => '*',
        'first'       => 0,
        'first-child' => 0,
        'last'        => -1,
        'last-child'  => -1,

        // @TODO:
        //
        // https://www.w3schools.com/cssref/sel_nth-child.asp
        // 'nth-child'   => ':',
        // 'odd'         => [':' => 2, +1],
        // 'even'        => [':' => 2],
        //
        // :nth-child(n) :nth-child(an+b)   p:nth-child(2)  Selects every <p> element that is the second child of its parent
        // :nth-last-child(n)  p:nth-last-child(2) Selects every <p> element that is the second child of its parent, counting from the last child
        //
        // :empty  p:empty Selects every <p> element that has no children
        // :only-child p:only-child    Selects every <p> element that is the only child of its parent
        // :not(selector)   :not(p) Selects every element that is not a <p> element
        //
        // :checked    input:checked   Selects every checked <input> element
        // :disabled   input:disabled  Selects every disabled <input> element
        // :enabled    input:enabled   Selects every enabled <input> element
        // :in-range    input:in-range  Selects <input> elements with a value within a specified range
        // :out-of-range    input:out-of-range  Selects <input> elements with a value outside a specified range
        //
        // :first-of-type   p:first-of-type Selects every <p> element that is the first <p> element of its parent
        // :last-of-type    p:last-of-type  Selects every <p> element that is the last <p> element of its parent
        // :nth-last-of-type(n)    p:nth-last-of-type(2)   Selects every <p> element that is the second <p> element of its parent, counting from the last child
        // :nth-of-type(n) p:nth-of-type(2)    Selects every <p> element that is the second <p> element of its parent
        // :only-of-type   p:only-of-type  Selects every <p> element that is the only <p> element of its parent
        // :root    root    Selects the document's root element
    );

    /**
     * Parse $this->s as a CSS selector
     *  tn1#id1[href] .cl1.cl2:first[x=y] tn2:5 , tn3.cl3 tn4#id2:eq(-1) > tn5:last-child > tn6:lt(3)
     *    -->
     *  [
     *     [
     *       { n: "tn1", i: "id1", a: [href: null] },
     *       { c: ["cl1","cl2"], p: [0], a: [x: 'y'] },
     *       { n: "tn2", p: [5] },
     *     ]
     *   , [
     *       { n: "tn3", c: ["cl3"] },
     *       { n: "tn4", i: "id2", p: [-1]   },
     *       { n: "tn5", p: [-1]   },
     *       { n: "tn6", p: [{"<":3}] },
     *     ]
     *  ]
     * @return array internal structure to be used by _find() and other methods
     */
    public function parse()
    {
        $ret = array();

        $desc = 0;
        $and  = 0;

        $this->skipWhitespace(); // we have trimmed $sel
        $sel = $this->s;
        while (!$this->isEOF()) {
            $_i = $this->i;

            $startDash = false;
            switch ($this->c) {
                case ',':
                    if (empty($ret)) {
                        $this->throwException('Not a valid selector', 1);
                    }
                    $this->inc();
                    $this->skipWhitespace();
                    ++$and;
                    $desc = 0;
                    break;

                case '*': // universal matcher
                    $this->inc();
                    empty($ret[$and][$desc]) and $ret[$and][$desc] = array();
                    break;

                case '#': // id
                    $this->inc();
                    $ret[$and][$desc]['i'] = $this->readName();
                    break;

                case '.': // class
                    $this->inc();
                    $ret[$and][$desc]['c'][] = $this->readName();
                    break;

                case '[': // attribute
                    $this->inc();
                    $a = $this->_parseAttr();
                    if (empty($ret[$and][$desc]['a'])) {
                        $ret[$and][$desc]['a'] = $a;
                    } else {
                        $t = $ret[$and][$desc]['a'];
                        foreach ($a as $k => $v) {
                            $t[$k] = $v;
                        }

                        $ret[$and][$desc]['a'] = $t;
                    }

                    break;

                case ':': // pseudo selector
                    $this->inc();
                    $ret[$and][$desc]['p'][] = $this->_parsePseudo();
                    break;

                case '-': // could be a start of an identifier
                    $this->inc();
                    $startDash = true;
                /*fallsthrough*/

                default:
                    // combinator (> + ~)
                    if ($this->inRange(self::$combinatorsRange)) {
                        if (empty($ret)) {
                            $this->throwException('Not a valid selector', 1);
                        }
                        ++$desc;
                        $ret[$and][$desc]['x'] = $this->c;
                        $this->inc();
                        $this->skipWhitespace();
                    }
                    // descendant (space) or combinator (> + ~)
                    elseif ($this->inRange(self::$spaceRange)) {
                        $this->skipWhitespace();
                        ++$desc;
                        if ($this->inRange(self::$combinatorsRange)) {
                            $ret[$and][$desc]['x'] = $this->c;
                            $this->inc();
                            $this->skipWhitespace();
                        }
                    }
                    // tag name
                    elseif ($this->isNameStart()) {
                        $n = $this->readName();
                        if ($startDash) {
                            $n         = '-' . $n;
                            $startDash = false;
                        }
                        $ret[$and][$desc]['n'] = $n;
                    } else {
                        $this->throwException("Unexpected '{$this->c}'", 2);
                    }
            }

            if ($_i == $this->i) {
                $this->throwException('Infinite loop', -1);
            }
        }

        return $ret;
    }

    /**
     * $this->s[$this->i-1] should be '['
     * @return array
     */
    protected function _parseAttr()
    {
        $this->skipWhitespace();
        $n = rtrim($this->readUntil('=]'));
        if (']' == $this->c || '' == $this->c) {
            $this->inc(); // ]
            return array($n => null);
        }

        $this->inc(); // =
        $this->skipWhitespace();

        if ($this->inRange('"\'')) {
            $q = $this->c;
            ++$this->i;
            $v = $this->readTo($q);
            ++$this->i;
            $this->skipWhitespace();
        } else {
            $v = rtrim($this->readTo(']'));
        }
        $this->inc();
        return array($n => $v);

        // $a = $this->readTo(']');
        // $this->inc();
        // $a = HTMLParser::parseAttrStr($a);
        // return $a;
    }

    /**
     * $this->s[$this->i-1] should be ':'
     * @return mixed
     */
    protected function _parsePseudo()
    {
        $a = $this->readName();

        $t = (int) $a;
        if ((string) $t === $a) {
            return $t;
        }

        if (isset(self::$pseudoMap[$a])) {
            $a = self::$pseudoMap[$a];
            if (is_int($a)) {
                return $a;
            }
        }

        if ('(' === $this->c) {
            $this->inc();
            $this->skipWhitespace();

            if ($this->inRange('"\'')) {
                $q = $this->c;
                ++$this->i;
                $t = $this->readTo($q);
                $this->inc();
                $this->skipWhitespace();
                if (')' != $this->c) {
                    $this->throwException("Unexpected {$this->c}", 2);
                }
            } else {
                $t = rtrim($this->readTo(')'));
            }

            $this->inc();
        } else {
            $t = null;
        }

        if ('eq' === $a) {
            if (!isset($t) || '' === $t) {
                $this->throwException(':eq() should have an argument', 3);
            }
            return (int) $t;
        }

        return array($a => $t);
    }

}
