<?php
use duzun\hQuery;

// -----------------------------------------------------
/**
 *  @author Dumitru Uzun (DUzun.Me)
 */
// -----------------------------------------------------
define('PHPUNIT_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR);
define('ROOT_DIR', strtr(dirname(PHPUNIT_DIR), '\\', '/') . '/');
if (!class_exists('PHPUnit_Runner_Version')) {
    class_alias('PHPUnit\Runner\Version', 'PHPUnit_Runner_Version');
}

// define('PHP_IS_NEW', version_compare(PHP_VERSION, '5.3.0') >= 0);
// -----------------------------------------------------
// if ( PHP_IS_NEW ) {
require_once ROOT_DIR . 'autoload.php';
require_once ROOT_DIR . 'vendor/autoload.php';
// }
// else {
//     require_once ROOT_DIR . 'hquery.php';
// }
// -----------------------------------------------------

// We have to make some adjustments for PHPUnit_BaseClass to work with
// PHPUnit 8.0 and still keep backward compatibility
if (version_compare(PHPUnit_Runner_Version::id(), '8.0.0') >= 0) {
    require_once PHPUNIT_DIR . '_PU8_TestCase.php';
} else {
    require_once PHPUNIT_DIR . '_PU7_TestCase.php';
}

// -----------------------------------------------------
// -----------------------------------------------------
/**
 * @backupGlobals disabled
 */
// -----------------------------------------------------
abstract class PHPUnit_BaseClass extends PU_TestCase
{
    /**
     * @var boolean
     */
    public static $log = true;

    /**
     * @var string
     */
    public static $testName;

    /**
     * @var string
     */
    public static $className;

    /**
     * @var string
     */
    public static $thausandsSeparator = "'";

    /**
     * @var array
     */
    public static $table_header = array();

    /**
     * @var array
     */
    public static $table_cols = array();

    /**
     * @var array
     */
    public static $table_align = array();

    /**
     * @var string
     */
    public static $CEL_SEP = ' | ';

    /**
     * @var string
     */
    public static $ROW_SEP = '---------------------';

    // -----------------------------------------------------
    /**
     * @param $header
     * @param NULL      $cols
     * @param NULL      $align
     */
    public static function print_table_header($header = null, $cols = null, $align = null)
    {
        if (isset($header)) {
            static::$table_header = $header;
        }

        if (!empty($cols)) {
            static::$table_cols = $cols+static::$table_cols;
        }
        if (!empty($align)) {
            static::$table_align = $align+static::$table_align;
        }

        $a = array('');
        foreach (static::$table_header as $i => $h) {
            $a[] = self::pad($h,
                isset(static::$table_cols[$i]) ? static::$table_cols[$i] : 6,
                STR_PAD_BOTH
            );
        }
        $a[] = '';

        $sep = array();
        foreach ($a as $b) {
            $sep[] = str_repeat('-', strlen($b) + substr_count($b, "\t") * 3);
        }
        $a   = rtrim(implode(static::$CEL_SEP, $a));
        $sep = rtrim(implode(static::$CEL_SEP, $sep));

        static::$ROW_SEP = $sep;

        echo PHP_EOL;
        echo $a, PHP_EOL;
        echo $sep, PHP_EOL;
    }

    /**
     * @param $row
     */
    public static function print_table_row($row, $align = null)
    {
        $a = array('');
        foreach ($row as $i => $c) {
            $a[] = self::pad(
                $c,
                isset(static::$table_cols[$i]) ? static::$table_cols[$i] : 6,
                !isset($align[$i])
                    ? !isset(static::$table_align[$i])
                    ? STR_PAD_LEFT
                    : static::$table_align[$i]
                    : $align[$i]
            );
        }
        $a[] = '';

        echo implode(static::$CEL_SEP, $a), PHP_EOL;
    }

    // -----------------------------------------------------
    // Before every test
    public function mySetUp()
    {
        self::$testName  = $this->getName();
        self::$className = get_class($this);

        // parent::mySetUp();
    }

    // -----------------------------------------------------
    /**
     * Asserts that a method exists.
     *
     * @param  string                                   $methodName
     * @param  string|object                            $className
     * @throws PHPUnit_Framework_AssertionFailedError
     */
    public static function assertMehodExists($methodName, $className, $message = '')
    {
        self::assertThat(method_exists($className, $methodName), self::isTrue(), $message);
    }

    // Alias to $this->assertMehodExists()
    public function assertClassHasMethod()
    {
        $args = func_get_args();
        return call_user_func_array(array($this, 'assertMehodExists'), $args);
    }

    // -----------------------------------------------------
    // Helper methods:

    /**
     * @var int
     */
    private static $_idx = 0;

    /**
     * @var string
     */
    private static $_lastTest;

    /**
     * @var string
     */
    private static $_lastClass;

    /**
     * Log a message to console
     */
    public static function log()
    {
        if (empty(self::$log)) {
            return;
        }

        if (self::$_lastTest != self::$testName || self::$_lastClass != self::$className) {
            echo PHP_EOL, PHP_EOL, '### -> ', self::$className . '::' . self::$testName, ' ()', PHP_EOL;
            self::$_lastTest  = self::$testName;
            self::$_lastClass = self::$className;
            self::$_idx       = 0;
        }
        $args = func_get_args();
        foreach ($args as $k => $v) {
            is_string($v) or is_int($v) or is_float($v) or $args[$k] = var_export($v, true);
        }

        echo ''
        // , PHP_EOL
        , ''
        , str_pad(++self::$_idx, 3, ' ', STR_PAD_LEFT)
        , ')  '
        , implode(' ', $args)
        , PHP_EOL
        ;
    }

    // -----------------------------------------------------
    public static function deleteTestData() {}

    // -----------------------------------------------------
    /**
     * @param  string $fn  filename
     * @return string file contents or false
     */
    public static function file_get_contents($fn)
    {
        $ffn = self::file_exists($fn);
        return $ffn ? file_get_contents($ffn) : false;
    }

    /**
     * @param  string    $fn filename
     * @return boolean
     */
    public static function file_exists($fn)
    {
        $ffn = PHPUNIT_DIR . $fn;
        if (!file_exists($ffn)) {
            $zfn = $ffn . '.gz';
            if (!file_exists($zfn)) {
                return false;
            }
            $gz   = file_get_contents($zfn);
            $data = hQuery::gzdecode($gz);
            if (!file_put_contents($ffn, $data)) {
                return false;
            }

        }
        return $ffn;
    }

    /**
     * @param  string   $fn filename
     * @return string
     */
    public static function fn($fn)
    {
        $ret = self::file_exists($fn) or $ret = $fn;
        return $ret;
    }

    // -----------------------------------------------------
    // Helpers:

    /**
     * @param  float    $num
     * @param  int      $dec
     * @return string
     */
    public static function fmtNumber($num, $dec = 0)
    {
        return number_format($num, $dec, '.', self::$thausandsSeparator);
    }

    /**
     * @param  float    $mt
     * @return string
     */
    public static function fmtMicroTime($mt)
    {
        $v = (string) self::fmtNumber(round($mt * 1e6), 0);
        return str_pad($v, 7, ' ', STR_PAD_LEFT) . 'µs';
    }

    /**
     * @param  float    $mm
     * @return string
     */
    public static function fmtMem($mm)
    {
        return self::fmtNumber($mm / 1024, $mm > 1024 ? $mm > 100 * 1024 ? 0 : 1 : 2) . 'KiB';
    }

    /**
     * @param float   $timer
     * @param boolean $fmt
     */
    public static function timer($timer = null, $fmt = true)
    {
        $mt = microtime(true);
        return isset($timer) ? $fmt ? self::fmtMicroTime($mt - $timer) : ($mt - $timer) * 1e6 : $mt;
    }

    /**
     * @param  float   $memer
     * @param  boolean $fmt
     * @return mixed
     */
    public static function memer($memer = null, $fmt = true)
    {
        $mm = memory_get_usage();
        if (isset($memer)) {
            $mm -= $memer;
            if ($fmt) {
                $mm = self::fmtMem($mm);
            }
        }
        return $mm;
    }

    /**
     * mb_str_pad
     *
     * @source https://gist.github.com/nebiros/226350
     * @author Kari "Haprog" Sderholm
     *
     * @param  string   $input
     * @param  int      $pad_length
     * @param  string   $pad_string
     * @param  int      $pad_type
     * @return string
     */
    public static function pad($input, $pad_length, $pad_type = STR_PAD_RIGHT, $pad_string = ' ')
    {
        $diff = strlen($input) - mb_strlen($input);
        return str_pad($input, $pad_length + $diff, $pad_string, $pad_type);
    }

    // -----------------------------------------------------
    /**
     * @param  array $list
     * @return int
     */
    public static function listMaxStrLen($list)
    {
        // return array_reduce($list, function ($i, $v) { return max($i, strlen($v)); }, 0);
        $ret = 0;
        foreach ($list as $v) {
            $ret = max($ret, strlen($v));
        }
        return $ret;
    }

    /**
     * @param  array $list
     * @return int
     */
    public static function listSumCounts($list)
    {
        // return array_reduce($list, function ($cary, $item) { return $cary + count($item); }, 0);
        $cary = 0;
        foreach ($list as $item) {
            $cary += count($item);
        }
        return $cary;
    }

    // -----------------------------------------------------
}
// -----------------------------------------------------
// Delete the temp test user after all tests have fired
register_shutdown_function('PHPUnit_BaseClass::deleteTestData');
// -----------------------------------------------------
