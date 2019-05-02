<?php
use duzun\hQuery;

// -----------------------------------------------------
/**
 *  @author DUzun.Me
 */
// -----------------------------------------------------
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '_PHPUnit_BaseClass.php';
// -----------------------------------------------------

class TestHQueryStress extends PHPUnit_BaseClass
{
    // -----------------------------------------------------
    /**
     * @var string
     */
    public static $className = 'hQuery';

    /**
     * @var boolean
     */
    public static $log = true;

    // -----------------------------------------------------
    /**
     * @var string
     */
    public static $thausandsSeparator = "'";

    // -----------------------------------------------------
    // -----------------------------------------------------
    public function test_construct_and_index()
    {
        $filename = 'data/big_granito_1.html';
        $tmr      = self::timer();
        $mmr      = self::memer();
        $html     = self::file_get_contents($filename);
        $mem      = self::memer($mmr);
        $exe      = self::timer($tmr);
        self::log('Loaded    ' . self::fmtNumber(strlen($html) / 1024, 2) . "Kb\tin\t{$exe}\t{$mem} RAM");

        $tmr = self::timer();
        $mmr = self::memer();
        $doc = new hQuery($html, false);
        $mem = self::memer($mmr);
        $exe = self::timer($tmr);
        self::log('Construct ' . self::fmtNumber($doc->size / 1024, 2) . "Kb\tin\t{$exe}\t{$mem} RAM");

        $doc->location(self::fn($filename));
        $tmr  = self::timer();
        $mmr  = self::memer();
        $tags = $doc->index();
        $mem  = self::memer($mmr);
        $exe  = self::timer($tmr);
        $time = version_compare(PHP_VERSION, '5.5.0') >= 0 ? 6e6 : 30e6; // travis runs PHP 5.4 slower for some reason
        $this->assertLessThan($time, self::timer($tmr, false), 'should index 3Mb in less then ' . ($time / 1e6) . ' sec');
        $count = self::fmtNumber(self::listSumCounts($tags));
        self::log("Indexed   {$count} tags\tin\t{$exe}\t{$mem} RAM");

        self::log("Original Charset: {$doc->charset}");

        $tags   = array_map('count', $tags);
        $counts = null;
        foreach ($tags as $k => $v) {
            $counts[$v] = (empty($counts[$v]) ? '' : $counts[$v] . ', ') . $k;
        }
        krsort($counts);

        // self::log('Tag counts:', $counts);

        return [$doc];
    }

    /**
     * @depends test_construct_and_index
     */
    public function test_find($return)
    {
        $doc = $return[0];

        $selectors = [
            'span',
            '.ch-title',
            '.even',
            '.row',
            'a',
            'img',
            'a img',
            'a>img',
            'a>img:parent',
            '.first',
            '.first:parent',
            '.first:next',
            'img.click',
        ];
        $max_len = self::listMaxStrLen($selectors);

        $contexts = [
            ' doc' => $doc,
            'body' => $doc->find('body'),
        ];

        foreach ($selectors as $sel) {
            foreach ($contexts as $name => $ctx) {
                $a   = null; // Free mem, call __destruct()
                $tmr = self::timer();
                $mmr = self::memer();
                $a   = $ctx->find($sel);
                $mem = self::memer($mmr);
                $exe = self::timer($tmr);
                $pad = str_repeat(' ', $max_len - strlen($sel));
                self::log("{$name}.find('$sel')$pad\t-> " . str_pad(count($a), 5, ' ', STR_PAD_LEFT) . "  in {$exe}");
                $this->assertNotNull($a);
            }
        }

        return $return;
    }

    // -----------------------------------------------------
    // -----------------------------------------------------
    // Helpers:

    /**
     * @param float $num
     * @param int   $dec
     */
    public static function fmtNumber($num, $dec = 0)
    {
        return number_format($num, $dec, '.', self::$thausandsSeparator);
    }

    /**
     * @param float $mt
     */
    public static function fmtMicroTime($mt)
    {
        $v = (string) self::fmtNumber(round($mt * 1e6), 0);
        return str_pad($v, 7, ' ', STR_PAD_LEFT) . 'µs';
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
                $mm = self::fmtNumber($mm / 1024, $mm > 1024 ? $mm > 100 * 1024 ? 0 : 1 : 2) . 'Kb';
            }
        }
        return $mm;
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
