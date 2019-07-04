<?php
namespace duzun\hQuery;

// ------------------------------------------------------------------------
/**
 *  Generic string parser class.
 *
 *  @internal
 *  @license MIT
 */
class Parser
{
    /**
     * @var string
     */
    public static $spaceRange = " \t\n\r\0\x0B\f";

    /**
     * Call ::_init_class() to init
     * @var string
     */
    public static $nameStartRange;

    /**
     * Call ::_init_class() to init
     * @var string
     */
    public static $nameRange;

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
     * Possition
     * @var int
     */
    public $i = 0;

    /**
     * Character at $this->i position
     * @var string
     */
    public $c;

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
        self::$nameStartRange = self::str_range('a-zA-Z_' . chr(128) . '-' . chr(255));

        // tag name chars
        self::$nameRange = self::str_range('0-9\-') . self::$nameStartRange;
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
     * @return int
     */
    public function skipWhitespace()
    {
        $i = $this->i;
        $s = $this->s;
        $j = strspn($s, self::$spaceRange, $i);
        if ($j) {
            $this->i = $i += $j;
            $this->c = $i >= $this->l ? '' : $s[$i];
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
        $j = strcspn($s, self::$spaceRange, $i);
        if ($j) {
            $this->i = $i += $j;
            $this->c = $i >= $this->l ? '' : $s[$i];
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
        $j = strspn($s, $range, $i);
        if ($j) {
            $n       = substr($s, $i, $j);
            $this->i = $i += $j;
            $this->c = $i >= $this->l ? '' : $s[$i];
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
        $j = strcspn($s, $range, $i);

        if ($j) {
            $n       = substr($s, $i, $j);
            $this->i = $i += $j;
            $this->c = $i >= $this->l ? '' : $s[$i];
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
                case '\\':{
                        $b       = substr($comp, $pos, 1);
                        $ret[$b] = $pos++;
                    }break;

                case '-':{
                        $c_ = ord($c = substr($comp, $pos, 1));
                        $b  = ord($b);
                        while ($b++ < $c_) {
                            $ret[chr($b)] = $pos;
                        }

                        while ($b-- > $c_) {
                            $ret[chr($b)] = $pos;
                        }

                    }break;

                default:{
                        $ret[$b = $c] = $pos;
                    }
            }
        }
        return implode('', array_keys($ret));
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
