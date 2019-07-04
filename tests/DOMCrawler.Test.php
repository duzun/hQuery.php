<?php
use duzun\hQuery;
use Symfony\Component\DomCrawler\Crawler;

// -----------------------------------------------------
/**
 * In this test-case I try to compare hQuery with Symfony's DomCrawler component performance wise.
 *
 *  @author DUzun.Me
 */
// -----------------------------------------------------
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '_PHPUnit_BaseClass.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'hQuery_stress.Test.php';
// -----------------------------------------------------

class TestDOMCrawler extends TestHQueryStress
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
        list($doc, $html) = parent::test_construct_and_index();
        $url              = $doc->location();

        // if ( $doc->charset != 'UTF-8' ) {
        //     $html = hQuery::convert_encoding($html, 'UTF-8', $doc->charset);
        // }
        $tmr = self::timer();
        $mmr = self::memer();
        $crw = new Crawler($html, hQuery::abs_url($url, 'https://example.com/'));
        $mem = self::memer($mmr);
        $exe = self::timer($tmr);
        self::log('   new DOMCrawler( ' . self::fmtNumber(strlen($html) / 1024 / 1024, 3) . "MiB )  \tin\t{$exe}\t{$mem} RAM");

        return array($doc, $crw);
    }

    /**
     * @var array
     */
    public static $table_header = array(
        'Selector',
        'found',
        'hQuery',
        'Crawler',
        'faster',
        'hQuery',
        'Crawler',
        'smaller',
    );

    /**
     * @var array
     */
    public static $table_cols = array(
        16,
        6,
        10, // TIME_LENGTH
        10, // TIME_LENGTH
        6,
        9, // MEM_LENGTH
        9, // MEM_LENGTH
        7,
    );

    /**
     * @var array
     */
    public static $table_align = array(
        STR_PAD_RIGHT,
        STR_PAD_LEFT,
        STR_PAD_LEFT,
        STR_PAD_LEFT,
        STR_PAD_RIGHT,
        STR_PAD_LEFT,
        STR_PAD_LEFT,
        STR_PAD_RIGHT,
    );

    /**
     * @depends test_construct_and_index
     */
    public function test_find($ctx)
    {
        list($hdoc, $cdoc) = $ctx;

        /**
         * @var array
         */
        static $selectors = array(
            'span',
            'span.glyphicon',
            'div',
            'p',
            'form',
            'td',
            // 'tr',
            'table',
            'table tr',
            'table>tr',
            'tr td',
            // 'tr>td', // @TODO: improve performance
            '.ch-title',
            '.even',
            '.row',
            'a',
            'img',
            'a img',
            // 'a>img', // @TODO: improve performance
            '.first',
            // '.first:next', // @TODO: improve performance
            'img.click',
            'script',
            '#current_page',
            'div#current_page',
        );
        self::$table_cols[0] = self::listMaxStrLen($selectors);

        $mapSelectors = array(
            ':next' => '+*',
        );

        self::log($hdoc->isDoc() ? '#document' : $hdoc->nodeName());

        self::print_table_header();

        $total = array(0, 0, 0, 0, 0);

        foreach ($selectors as $sel) {
            // hQuery:

            $a    = null; // Free mem, call __destruct()
            $tmr  = self::timer();
            $mmr  = self::memer();
            $a    = $hdoc->find($sel);
            $amem = self::memer($mmr, false);
            $aexe = self::timer($tmr, false);
            $this->assertNotNull($a);

            // DOMCrawler:

            $wsel = strtr($sel, $mapSelectors);
            $b    = null; // Free mem, call __destruct()
            $tmr  = self::timer();
            $mmr  = self::memer();
            $b    = $cdoc->filter($wsel);
            $bmem = self::memer($mmr, false);
            $bexe = self::timer($tmr, false);

            // Compare:
            // $this->assertGreaterThan($aexe, $bexe, $sel);
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
                'x' . round($bexe / $aexe),
                self::fmtMem($amem),
                self::fmtMem($bmem),
                'x' . round($bmem / $amem, 1),
            ));
        }

        $count = count($selectors);

        echo self::$ROW_SEP, PHP_EOL;

        self::print_table_row(array(
            'Average:',
            round($total[0] / $count),
            self::fmtMicroTime($total[1] / $count / 1e6),
            self::fmtMicroTime($total[2] / $count / 1e6),
            'x' . round($total[2] / $total[1]),
            self::fmtMem($total[3] / $count),
            self::fmtMem($total[4] / $count),
            'x' . round($total[4] / $total[3]),
        ), array(STR_PAD_LEFT));

        self::print_table_row(array(
            'Total:',
            $total[0],
            self::fmtNumber($total[1] / 1e3) . 'ms',
            self::fmtNumber($total[2] / 1e3) . 'ms',
            '-',
            self::fmtMem($total[3]),
            self::fmtMem($total[4]),
            '-',
        ), array(STR_PAD_LEFT));

        echo PHP_EOL;

        $this->assertGreaterThan($total[1] * 5, $total[2], 'hQuery should be at least x10 faster than DOMCrawler');

        return $ctx;
    }

    // /**
    //  * @depends test_find
    //  */
    // public function test_body_find($ctx)
    // {
    //     list($hdoc, $cdoc) = $ctx;
    //     return $this->test_find(array($hdoc->find('body'), $cdoc->filter('body')));
    // }

    // -----------------------------------------------------

}
