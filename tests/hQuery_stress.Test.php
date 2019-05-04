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
     * @var boolean
     */
    public static $log = true;

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
        self::log('        load_file( ' . self::fmtNumber(strlen($html) / 1024 / 1024, 3) . "MiB )  \tin\t{$exe}\t{$mem} RAM");

        $tmr = self::timer();
        $mmr = self::memer();
        $doc = new hQuery($html, false);
        $mem = self::memer($mmr);
        $exe = self::timer($tmr);
        self::log('       new hQuery( ' . self::fmtNumber($doc->size / 1024 / 1024, 3) . "MiB )   \tin\t{$exe}\t{$mem} RAM");

        $doc->location(self::fn($filename));
        $tmr  = self::timer();
        $mmr  = self::memer();
        $tags = $doc->index();
        $mem  = self::memer($mmr);
        $exe  = self::timer($tmr);
        $time = version_compare(PHP_VERSION, '5.5.0') >= 0 ? 6e6 : 30e6; // travis runs PHP 5.4 slower for some reason
        $this->assertLessThan($time, self::timer($tmr, false), 'should index 3Mb in less then ' . ($time / 1e6) . ' sec');
        $count = self::fmtNumber(self::listSumCounts($tags));
        self::log("    hQuery->index( {$count} tags )\tin\t{$exe}\t{$mem} RAM");

        self::log("   Original Charset: {$doc->charset}");

        $tags   = array_map('count', $tags);
        $counts = null;
        foreach ($tags as $k => $v) {
            $counts[$v] = (empty($counts[$v]) ? '' : $counts[$v] . ', ') . $k;
        }
        krsort($counts);

        // self::log('Tag counts:', $counts);
        return array($doc, $html);
    }

    /**
     * @depends test_construct_and_index
     */
    public function test_find($return)
    {
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
        $max_len = self::listMaxStrLen($selectors);

        $contexts = array(
            ' doc' => $doc,
            'body' => $doc->find('body'),
        );

        foreach ($selectors as $sel) {
            $c = array();
            $w = array();
            foreach ($contexts as $name => $ctx) {
                $a        = null; // Free mem, call __destruct()
                $tmr      = self::timer();
                $mmr      = self::memer();
                $a        = $ctx->find($sel);
                $mem      = self::memer($mmr);
                $exe      = self::timer($tmr);
                $c[$name] = array($a, $exe, $mem);
                // $pad = str_repeat(' ', $max_len - strlen($sel));
                // self::log("{$name}.find('$sel')$pad\t-> " . str_pad(count($a), 5, ' ', STR_PAD_LEFT) . "  in {$exe}  with {$mem}");
                $this->assertNotNull($a);
            }
            $a  = reset($c);
            $ak = trim(key($c));
            $b  = next($c);
            $bk = trim(key($c));

            $pad = str_repeat(' ', $max_len - strlen($sel));
            self::log("count(\$c.find('$sel'))$pad\t= " . str_pad(count($a[0]), 5, ' ', STR_PAD_LEFT) . " in {$a[1]} (\$c={$ak}), {$b[1]} (\$c={$bk})  mem: {$a[2]}");

            $this->assertEquals(count($a[0]), count($b[0]), $sel);
        }

        return $return;
    }

    // -----------------------------------------------------

}
