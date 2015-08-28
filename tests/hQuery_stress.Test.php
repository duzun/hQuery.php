<?php
// -----------------------------------------------------
/**
 *  @author DUzun.Me
 *
 */
// -----------------------------------------------------
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '_PHPUnit_BaseClass.php';
// -----------------------------------------------------

class TestHQueryStress extends PHPUnit_BaseClass {
    // -----------------------------------------------------
    public static $className = 'hQuery';
    public static $log       = true;

    // -----------------------------------------------------
    public static $thausandsSeparator = "'";

    // -----------------------------------------------------
    // -----------------------------------------------------
    public function test_construct_and_index() {
        $filename = dirname(__FILE__) . '/data/big_granito_1.html';
        $tmr = self::timer();
        $mmr = self::memer();
        $html = file_get_contents($filename);
        $mem = self::memer($mmr);
        $exe = self::timer($tmr);
        self::log( "Loaded    " . self::fmtNumber(strlen($html) / 1024, 2) . "Kb\tin\t{$exe}\t{$mem} RAM");

        $tmr = self::timer();
        $mmr = self::memer();
        $doc = new hQuery($html, false);
        $mem = self::memer($mmr);
        $exe = self::timer($tmr);
        self::log( "Construct " . self::fmtNumber($doc->size / 1024, 2) . "Kb\tin\t{$exe}\t{$mem} RAM");

        $doc->location($filename);
        $tmr = self::timer();
        $mmr = self::memer();
        $tags = $doc->index();
        $mem = self::memer($mmr);
        $exe = self::timer($tmr);
        $this->assertLessThan(6000000, self::timer($tmr, false), 'should index 3Mb in less then 3 sec');
        $count = self::fmtNumber(array_reduce($tags, function ($cary, $item) { return $cary + count($item); }, 0));
        self::log( "Indexed   {$count} tags\tin\t{$exe}\t{$mem} RAM" );

        self::log("Original Charset: {$doc->charset}");

        $tags = array_map('count', $tags);
        $counts = NULL;
        foreach($tags as $k => $v) {
            $counts[$v] = (empty($counts[$v])?'':$counts[$v].', ') . $k;
        }
        krsort($counts);

        // self::log('Tag counts:', $counts);

        return array($doc);
    }

    /**
     * @depends test_construct_and_index
     */
    public function test_find($return) {
        $doc = $return[0];

        $selectors = array(
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
        );
        $max_len = array_reduce($selectors, function ($i, $v) { return max($i, strlen($v)); }, 0);

        $contexts = array(
            ' doc' => $doc,
            'body' => $doc->find('body'),
        );

        foreach($selectors as $sel) {
            foreach($contexts as $name => $ctx) {
                $a = NULL; // Free mem, call __destruct()
                $tmr = self::timer();
                $mmr = self::memer();
                $a = $ctx->find($sel);
                $mem = self::memer($mmr);
                $exe = self::timer($tmr);
                $pad = str_repeat(' ', $max_len - strlen($sel));
                self::log("{$name}.find('$sel')$pad\t-> ".str_pad(count($a), 5, ' ', STR_PAD_LEFT)."  in {$exe}");
            }
        }

        return $return;
    }

    // -----------------------------------------------------
    // -----------------------------------------------------
    // Helpers:

    public static function fmtNumber($num, $dec=0) {
        return number_format($num, $dec, '.', self::$thausandsSeparator);
    }

    public static function fmtMicroTime($mt) {
        $v = (string)self::fmtNumber(round( $mt * 1e6 ), 0);
        return str_pad($v, 7, ' ', STR_PAD_LEFT) . 'µs';
    }

    public static function timer($timer=NULL, $fmt=true) {
        $mt = microtime(true);
        return isset($timer) ? $fmt ? self::fmtMicroTime($mt - $timer) : ($mt - $timer) * 1e6 : $mt;
    }

    public static function memer($memer=NULL, $fmt=true) {
        $mm = memory_get_usage();
        if ( isset($memer) ) {
            $mm -= $memer;
            if ( $fmt ) {
                $mm = self::fmtNumber($mm/1024, $mm > 1024 ? $mm > 100 * 1024 ? 0 : 1 : 2).'Kb';
            }
        }
        return $mm;
    }

    // -----------------------------------------------------

}
?>