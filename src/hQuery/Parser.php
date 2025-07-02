<?php

namespace duzun\hQuery;

// ------------------------------------------------------------------------
/**
 *  Generic string parser class.
 *
 *  @internal
 *  @license MIT
 */
abstract class Parser
{
    /**
     * @var string
     */
    public static $spaceRange = " \t\n\r\0\x0B\f";
    public static $spaceRangeArr;

    /**
     * Call ::_init_class() to init
     * @var string
     */
    public static $nameStartRange;
    public static $nameStartRangeArr;

    /**
     * Call ::_init_class() to init
     * @var string
     */
    public static $nameRange;
    public static $nameRangeArr;

    /**
     * The string to parse
     * @var string
     */
    public $s;

    /**
     * Length to parse up to
     * @var int
     */
    public $l;

    /**
     * Position
     * @var int
     */
    public $i = 0;

    /**
     * Character at $this->i position
     * @var string
     */
    public $c;

    /**
     * To be implemented in extended class
     */
    abstract public function parse();

    /**
     * @param string $str
     * @param int    $start
     * @param int    $len
     */
    public function __construct($str, $start = 0, $len = null)
    {
        $this->i = (int) $start;
        $this->s = $str = is_string($str) ? $str : (string) $str;
        $l       = strlen($str);
        $this->l = isset($len) && $len < $l ? $len : $l;

        if ($this->l < 0) {
            $this->l += $l;
        }

        $this->c = $this->isEOF() ? '' : $str[$this->i];

        isset(self::$nameStartRange) or self::_init_class();
    }

    protected static function _init_class()
    {
        // first letter chars
        $nameStartRange = 'a-zA-Z_' . chr(128) . '-' . chr(255);
        self::$nameStartRange = self::str_range($nameStartRange);
        self::$nameStartRangeArr = self::arr_range($nameStartRange);

        // tag name chars
        $nameRange = '\-0-9';
        self::$nameRange = self::str_range($nameRange) . self::$nameStartRange;
        self::$nameRangeArr = self::arr_range($nameStartRange.$nameRange);

        self::$spaceRangeArr = self::arr_range(self::$spaceRange);
    }

    /**
     * @return bool TRUE if EOF
     */
    public function isEOF()
    {
        return $this->i >= $this->l;
    }

    /**
     * @param  int  $by
     * @return bool TRUE if not EOF
     */
    public function inc($by = 1)
    {
        $i = $this->i;

        if (!$by) {
            return $i < $this->l;
        }

        $i += $by;
        $this->i = $i;
        if ($i < $this->l) {
            $this->c = $this->s[$i];
            return true;
        }
        $this->c = '';
        return false;
    }

    /**
     * @param  string $range
     * @param  string $char    Should be one char
     * @return bool
     */
    public function inRange($range, $char = null)
    {
        isset($char) or $char = $this->c;
        return strpos($range, $char) !== false;
    }

    /**
     * @param  string $char Should be one char
     * @return bool
     */
    public function isNameStart($char = null)
    {
        return $this->inRange(self::$nameStartRange, $char);
    }

    /**
     * @param  string $char Should be one char
     * @return bool
     */
    public function isWhitespace($char = null)
    {
        return $this->inRange(self::$spaceRange, $char);
    }

    /**
     * Is $str containing only white-spaces?
     *
     * @param string $str
     * @return bool
     */
    public static function is_whitespace($str)
    {
        return strspn($str, self::$spaceRange) == strlen($str);
    }

    /**
     * @return int
     */
    public function skipWhitespace()
    {
        $i = $this->i;
        $s = $this->s;
        $l = $this->l;
        $j = strspn($s, self::$spaceRange, $i, $l-$i);
        if ($j) {
            $this->i = $i += $j;
            $this->c = $i >= $l ? '' : $s[$i];
        }
        return $this->i;
    }

    /**
     * @return int
     */
    public function skipToWhitespace()
    {
        $i = $this->i;
        $s = $this->s;
        $l = $this->l;
        $j = strcspn($s, self::$spaceRange, $i, $l-$i);
        if ($j) {
            $this->i = $i += $j;
            $this->c = $i >= $l ? '' : $s[$i];
        }
        return $this->i;
    }

    /**
     * @return string
     */
    public function readToWhitespace()
    {
        return $this->readUntil(self::$spaceRange);
    }

    /**
     * @param  string $substr
     * @return int
     */
    public function skipTo($substr)
    {
        $s = $this->s;
        $i = strpos($s, $substr, $this->i);
        if (false === $i) {
            $i       = $this->l; // EOF
            $this->c = '';
        } else {
            $this->c = $s[$i];
        }
        $this->i = $i;

        return $i;
    }

    /**
     * @param  string   $range
     * @return string
     */
    public function readWhile($range)
    {
        $i = $this->i;
        $s = $this->s;
        $l = $this->l;
        $j = strspn($s, $range, $i, $l-$i);
        if ($j) {
            $n       = substr($s, $i, $j);
            $this->i = $i += $j;
            $this->c = $i >= $l ? '' : $s[$i];
        } else {
            $n = '';
        }
        return $n;
    }

    /**
     * @param  string   $range
     * @return string
     */
    public function readUntil($range)
    {
        $i = $this->i;
        $s = $this->s;
        $l = $this->l;
        $j = strcspn($s, $range, $i, $l-$i);

        if ($j) {
            $n       = substr($s, $i, $j);
            $this->i = $i += $j;
            $this->c = $i >= $l ? '' : $s[$i];
        } else {
            $n = '';
        }
        return $n;
    }

    /**
     * @param  string   $substr
     * @return string
     */
    public function readTo($substr)
    {
        $i = $this->i;
        $s = $this->s;
        $j = strpos($s, $substr, $i);
        if (false === $j) {
            $j       = $this->l; // EOF
            $this->c = '';
        } else {
            $this->c = $s[$j];
        }
        $this->i = $j;

        return substr($s, $i, $j - $i);
    }

    /**
     * @return string
     */
    public function readName()
    {
        // Faster version:
        return $this->readWhile(self::$nameRange);

        // Slower version:
        $j =
        $i = $this->i;
        $l = $this->l;
        if ($j >= $l) {
            return '';
        }

        $s = $this->s;
        $c = $s[$j];
        while (
            $c > 127 ||
            'a' <= $c && $c <= 'z' ||
            'A' <= $c && $c <= 'Z' ||
            '0' <= $c && $c <= '9' ||
            '-' == $c ||
            '_' == $c
        ) {
            ++$j;
            if ($j >= $l) {
                $c = '';
                break;
            }
            $c = $s[$j];
        }
        $this->i = $j;
        $this->c = $c;

        return substr($s, $i, $j - $i);
    }

    // ------------------------------------------------------------------------
    // ------------------------------------------------------------------------
    /**
     * Expand ranges in a compact string.
     * Ex: 'a-f' -> 'abcdef'
     *
     * @param string $comp A compact string with ranges
     * @param int    $pos  Start with this position in $comp
     * @param int    $len  If defined, expand ranges only up to position $len in $comp.
     */
    public static function str_range($comp, $pos = 0, $len = null)
    {
        $ret = array();
        $b   = strlen($comp);
        if (!isset($len) || $len > $b) {
            $len = $b;
        }

        $b = "\x0";
        while ($pos < $len) {
            switch ($c = $comp[$pos++]) {
                case '-': {
                        $c = substr($comp, $pos++, 1);
                        $c_ = ord($c);
                        $b  = ord($b);
                        while ($b++ < $c_) {
                            $ret[chr($b)] = $pos;
                        }

                        while ($b-- > $c_) {
                            $ret[chr($b)] = $pos;
                        }
                    }
                    break;

                case '\\':
                    $c = substr($comp, $pos++, 1);

                default:
                    $ret[$b = $c] = $pos;
            }
        }
        return implode('', array_keys($ret));
    }

    // ------------------------------------------------------------------------
    /**
     * Expand a string with ranges to a sorted array of ranges.
     * Ex: 'a-f' -> ['a' => 'f'],
     *     '12345789' => ['1' => '5', '7' => '9']
     *
     * @param string $comp A compact string with ranges
     * @param int    $pos  Start with this position in $comp
     * @param int    $len  If defined, expand ranges only up to position $len in $comp.
     */
    public static function arr_range($comp, $pos = 0, $len = null)
    {
        $ret = array();
        $b   = strlen($comp);
        if (!isset($len) || $len > $b) {
            $len = $b;
        }

        $k =
        $b = "\x0";
        while ($pos < $len) {
            switch ($c = $comp[$pos++]) {
                case '-':
                    $c = substr($comp, $pos++, 1);
                    if ($b < $c) {
                        $ret[$k] = $c;
                    } else {
                        $ret[$k = $c] = $b;
                    }
                    break;

                case '\\':
                    $c = substr($comp, $pos++, 1);

                default:
                    ++$b;
                    if ($b == $c) {
                        $ret[$k] = $c;
                    } else {
                        $k = $b = $c;
                        $ret[$b] = $c;
                    }
            }
        }
        ksort($ret, SORT_STRING);
        $k = $l = '';
        foreach ($ret as $b => $c) {
            is_int($b) && $b .= '';
            if ($k !== '') {
                $l = chr(ord($l)+1);
                if ($b <= $l) {
                    if ($c >= $l) {
                        $ret[$k] = $l = $c;
                    }
                    unset($ret[$b]);
                    continue;
                }
            }
            $k = $b;
            $l = $c;
        }

        return $ret;
    }

    public static function arrspn($str, $arrange, $pos=0, $len=null) {
        isset($len) or $len = strlen($str);
        $i = $pos;
        $l = $i + $len;
        while($i < $l) {
            $c = $str[$i];
            $found = false;
            foreach($arrange as $b => $e) {
                is_string($b) or $b = (string)$b;
                if ($c < $b) {
                    continue;
                }
                if ($c <= $e) {
                    $found = true;
                    break;
                }
            }
            if(!$found) break;
            ++$i;
        }
        return $i - $pos;
    }

    public static function spacespn($str, $pos=0, $len=null) {
        return self::arrspn($str, self::$spaceRangeArr, $pos, $len);
    }

    public static function wsspn($str, $pos=0, $len=null) {
        isset($len) or $len = strlen($str);
        $i = $pos;
        $l = $i + $len;
        while($i < $l and !($c = ord($str[$i])) || $c == 32 || 9 <= $c && $c <= 13) {
            ++$i;
        }
        return $i - $pos;
    }

    public static function tagnamespn($str, $pos=0, $len=null) {
        isset($len) or $len = strlen($str);
        $i = $pos;
        $l = $i + $len;
        while($i < $l) {
            $c = $str[$i];
            $isOk = 'a' <= $c && $c <= 'z' ||
                    'A' <= $c && $c <= 'Z' ||
                    '0' <= $c && $c <= ':' ||
                    $c == '-' || $c == '_';
            if(!$isOk) {
                $c = ord($c);
                $isOk = 128 <= $c && $c <= 255;
                if (!$isOk) break;
            }
            ++$i;
        }
        return $i - $pos;
    }

    // ------------------------------------------------------------------------
    /**
     * @param $msg
     * @param $code
     */
    public function throwException($msg, $code = 0)
    {
        throw new \Exception(get_called_class() . ": $msg at {$this->i} in \"{$this->s}\"", $code);
    }

}
