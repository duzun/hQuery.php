<?php
namespace Tests\Shared;

use duzun\hQuery;

/**
 * Base trait for test functionality
 */
trait TestBaseTrait
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
     * @var mixed
     */
    public static $inst;

    /**
     * @var array
     */
    protected static $_data = array();

    /**
     * @var array
     */
    protected static $_tmpFiles = array();

    /**
     * @var array
     */
    protected static $_tmpDirs = array();

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
     * Log a message
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
        , str_pad(++self::$_idx, 3, ' ', STR_PAD_LEFT)
        , ')  '
        , implode(' ', $args)
        , PHP_EOL;
    }

    /**
     * Create a temporary file
     */
    public static function tmpfile($contents = null, $ext = null)
    {
        $dir = sys_get_temp_dir();
        $ext = $ext ? '.' . ltrim($ext, '.') : '';
        $file = tempnam($dir, 'test_');
        if ($file === false) {
            throw new \RuntimeException("Could not create temporary file in {$dir}");
        }
        if ($ext) {
            rename($file, $file .= $ext);
        }
        self::$_tmpFiles[] = $file;
        if (isset($contents)) {
            file_put_contents($file, $contents);
        }
        return $file;
    }

    /**
     * Create a temporary directory
     */
    public static function tmpdir($prefix = 'test_')
    {
        $dir = sys_get_temp_dir();
        $path = $dir . DIRECTORY_SEPARATOR . uniqid($prefix);
        if (!mkdir($path)) {
            throw new \RuntimeException("Could not create temporary directory {$path}");
        }
        self::$_tmpDirs[] = $path;
        return $path;
    }

    /**
     * Delete test data
     */
    public static function deleteTestData()
    {
        foreach (self::$_tmpFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        foreach (self::$_tmpDirs as $dir) {
            if (is_dir($dir)) {
                self::rmdir($dir);
            }
        }
    }

    /**
     * Recursively remove a directory
     */
    protected static function rmdir($dir)
    {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                is_dir($path) ? self::rmdir($path) : unlink($path);
            }
            return rmdir($dir);
        }
        return false;
    }

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

    /**
     * @param  string $filename  filename
     * @return string duzun\hQuery
     */
    public static function load_doc_from_file($filename) {
        $tmr      = self::timer();
        $mmr      = self::memer();
        $html     = self::file_get_contents($filename);
        $mem      = self::memer($mmr);
        $exe      = self::timer($tmr);
        self::log('        load_file( ' . self::fmtNumber(strlen($html) / 1024 / 1024, 3) . "MiB )  \tin\t{$exe}\t{$mem} RAM");

        $tmr = self::timer();
        $mmr = self::memer();
        $doc = new hQuery($html, false);
        $mem = self::memer($mmr);
        $exe = self::timer($tmr);
        self::log('       new hQuery( ' . self::fmtNumber($doc->size / 1024 / 1024, 3) . "MiB )   \tin\t{$exe}\t{$mem} RAM");

        $doc->location(self::fn($filename));

        return array($doc, $html);
    }

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
        $ffn = dirname(__FILE__, 3) . DIRECTORY_SEPARATOR . $fn;
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

    /**
     * @param  float    $num
     * @param  int      $dec
     * @return string
     */
    public static function fmtNumber($num, $dec = 0)
    {
        return number_format($num, $dec, '.', "'");
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

    /**
     * @param  array $list
     * @return int
     */
    public static function listMaxStrLen($list)
    {
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
        $cary = 0;
        foreach ($list as $item) {
            $cary += count($item);
        }
        return $cary;
    }
}
