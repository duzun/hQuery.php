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
    /**
     * @var array
     */
    public static $table_header = array(
        'Selector',
        'found',
        'doc exe',
        'body exe',
        'doc mem',
        'body mem',
    );

    /**
     * @var array
     */
    public static $table_cols = array(
        16,
        6,
        10, // TIME_LENGTH
        10, // TIME_LENGTH
            // 6,
        9,  // MEM_LENGTH
        9,  // MEM_LENGTH
            // 7,
    );

    /**
     * @var array
     */
    public static $table_align = array(
        STR_PAD_RIGHT,
        STR_PAD_LEFT,
        STR_PAD_LEFT,
        STR_PAD_LEFT,
        // STR_PAD_RIGHT,
        STR_PAD_LEFT,
        STR_PAD_LEFT,
        // STR_PAD_RIGHT,
    );
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
            'span.glyphicon',
            'div',
            'p',
            'form',
            'td',
            'tr',
            'table',
            'table tr',
            'table>tr',
            'tr td',
            'tr>td', // @TODO: improve performance
            '.ch-title',
            '.even',
            '.row',
            'a',
            'a[href]',
            'img',
            'img[src]',
            'a img',
            'a>img',
            'a>img:parent',
            'a[href]>img[src]:parent',
            '.first',
            '.first:parent',
            '.first:next',
            'img.click',
            'script',
        );
        self::$table_cols[0] = self::listMaxStrLen($selectors);
        $max_len             = self::listMaxStrLen($selectors);

        self::print_table_header();
        $total = array(0, 0, 0, 0, 0);

        $body = $doc->find('body');

        foreach ($selectors as $sel) {
            $c = array();
            $w = array();

            $a    = null; // Free mem, call __destruct()
            $tmr  = self::timer();
            $mmr  = self::memer();
            $a    = $doc->find($sel);
            $amem = self::memer($mmr, false);
            $aexe = self::timer($tmr, false);
            $this->assertNotNull($a, $sel);

            $b    = null; // Free mem, call __destruct()
            $tmr  = self::timer();
            $mmr  = self::memer();
            $b    = $body->find($sel);
            $bmem = self::memer($mmr, false);
            $bexe = self::timer($tmr, false);
            $this->assertNotNull($b, $sel);

            $this->assertEquals(count($a), count($b), $sel);

            $total[0] += count($a);
            $total[1] += $aexe;
            $total[2] += $bexe;
            $total[3] += $amem;
            $total[4] += $bmem;

            self::print_table_row(array(
                $sel,
                count($a),
                self::fmtMicroTime($aexe / 1e6),
                self::fmtMicroTime($bexe / 1e6),
                // 'x' . round($bexe / $aexe),
                self::fmtMem($amem),
                self::fmtMem($bmem),
                // 'x' . round($bmem / $amem, 1),
            ));
        }

        $count = count($selectors);

        echo self::$ROW_SEP, PHP_EOL;

        self::print_table_row(array(
            'Average:',
            round($total[0] / $count),
            self::fmtMicroTime($total[1] / $count / 1e6),
            self::fmtMicroTime($total[2] / $count / 1e6),
            // 'x' . round($total[2] / $total[1]),
            self::fmtMem($total[3] / $count),
            self::fmtMem($total[4] / $count),
            // 'x' . round($total[4] / $total[3]),
        ), array(STR_PAD_LEFT));

        self::print_table_row(array(
            'Total:',
            $total[0],
            self::fmtNumber($total[1] / 1e3) . 'ms',
            self::fmtNumber($total[2] / 1e3) . 'ms',
            // '-',
            self::fmtMem($total[3]),
            self::fmtMem($total[4]),
            // '-',
        ), array(STR_PAD_LEFT));

        echo PHP_EOL;

        return $return;
    }

    // -----------------------------------------------------

}
