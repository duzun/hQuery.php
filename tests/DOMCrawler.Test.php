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
        $url = $doc->location();

        // if ( $doc->charset != 'UTF-8' ) {
        //     $html = hQuery::convert_encoding($html, 'UTF-8', $doc->charset);
        // }
        $tmr = self::timer();
        $mmr = self::memer();
        $crw = new Crawler($html, $url);
        $mem = self::memer($mmr);
        $exe = self::timer($tmr);
        self::log('   new DOMCrawler( ' . self::fmtNumber(strlen($html) / 1024 / 1024, 3) . "MiB )  \tin\t{$exe}\t{$mem} RAM");

        return [$doc, $crw];
    }

    /**
     * @depends test_construct_and_index
     */
    public function test_find($ctx)
    {
        list($hdoc, $cdoc) = $ctx;

        static $selectors = [
            'span',
            'span.glyphicon',
            'div',
            'p',
            'td',
            'tr',
            'table',
            'script',
            'form',
            'table tr',
            'table>tr',
            'tr td',
            'tr>td',
            '.ch-title',
            '.even',
            '.row',
            'a',
            'img',
            'a img',
            'a>img',
            '.first',
            '.first:next',
            'img.click',
            '#current_page',
            'div#current_page',
        ];
        $max_len = self::listMaxStrLen($selectors);
        $mapSelectors = [
            ':next' => '+*',
        ];

        $TIME_LENGTH = 10;
        $MEM_LENGTH = 9;
        $CEL_SEP = " | ";
        $a = [
            '',
            self::pad('Selector' , $max_len, ' ', STR_PAD_BOTH),
            self::pad('found'    , 6, ' ', STR_PAD_BOTH),
            self::pad('hQuery'   , $TIME_LENGTH, ' ', STR_PAD_BOTH),
            self::pad('Crawler'  , $TIME_LENGTH, ' ', STR_PAD_BOTH),
            self::pad('faster'   , 6, ' ', STR_PAD_BOTH),
            self::pad('hQuery'   , $MEM_LENGTH, ' ', STR_PAD_BOTH),
            self::pad('Crawler'  , $MEM_LENGTH, ' ', STR_PAD_BOTH),
            self::pad('smaller'  , 7, ' ', STR_PAD_BOTH),
            '',
        ];
        $sep = [];
        foreach($a as $b) {
            $sep[] = str_repeat('-', strlen($b) + substr_count($b, "\t") * 3);
        }
        $a = rtrim(implode($CEL_SEP, $a));
        $sep = rtrim(implode($CEL_SEP, $sep));

        self::log($hdoc->isDoc() ? '#document' : $hdoc->nodeName());

        echo PHP_EOL;
        echo $a, PHP_EOL;
        echo $sep, PHP_EOL;

        $total = [0,0,0,0,0];

        foreach ($selectors as $sel) {
                // hQuery
                $a   = null; // Free mem, call __destruct()
                $tmr = self::timer();
                $mmr = self::memer();
                $a   = $hdoc->find($sel);
                $amem = self::memer($mmr, false);
                $aexe = self::timer($tmr, false);
                $this->assertNotNull($a);

                // DOMCrawler
                $b   = null; // Free mem, call __destruct()
                $tmr = self::timer();
                $mmr = self::memer();
                $b   = $cdoc->filter(strtr($sel, $mapSelectors));
                $bmem = self::memer($mmr, false);
                $bexe = self::timer($tmr, false);

                $this->assertGreaterThan($aexe, $bexe, $sel);
                $this->assertEquals(count($a), count($b), $sel);

                $total[0] += count($a);
                $total[1] += $aexe;
                $total[2] += $bexe;
                $total[3] += $amem;
                $total[4] += $bmem;

                echo implode($CEL_SEP, [
                    '',
                    self::pad($sel, $max_len, ' ', STR_PAD_RIGHT),
                    self::pad(count($a), 6, ' ', STR_PAD_LEFT),
                    self::pad(self::fmtMicroTime($aexe / 1e6), $TIME_LENGTH, ' ', STR_PAD_LEFT),
                    self::pad(self::fmtMicroTime($bexe / 1e6), $TIME_LENGTH, ' ', STR_PAD_LEFT),
                    self::pad('x'.round($bexe / $aexe), 6, ' ', STR_PAD_RIGHT),
                    self::pad(self::fmtMem($amem), $MEM_LENGTH, ' ', STR_PAD_LEFT),
                    self::pad(self::fmtMem($bmem), $MEM_LENGTH, ' ', STR_PAD_LEFT),
                    self::pad('x'.round($bmem / $amem, 1), 7, ' ', STR_PAD_RIGHT),
                    '',
                ]), PHP_EOL;
        }

        $count = count($selectors);

        echo $sep, PHP_EOL;

        echo implode($CEL_SEP, [
            '',
            self::pad('Average:', $max_len, ' ', STR_PAD_LEFT),
            self::pad(round($total[0] / $count), 6, ' ', STR_PAD_LEFT),
            self::pad(self::fmtMicroTime($total[1] / $count / 1e6), $TIME_LENGTH, ' ', STR_PAD_LEFT),
            self::pad(self::fmtMicroTime($total[2] / $count / 1e6), $TIME_LENGTH, ' ', STR_PAD_LEFT),
            self::pad('x'.round($total[2] / $total[1]), 6, ' ', STR_PAD_RIGHT),
            self::pad(self::fmtMem($total[3] / $count), $MEM_LENGTH, ' ', STR_PAD_LEFT),
            self::pad(self::fmtMem($total[4] / $count), $MEM_LENGTH, ' ', STR_PAD_LEFT),
            self::pad('x'.round($total[4] / $total[3]), 7, ' ', STR_PAD_RIGHT),
            '',
        ]), PHP_EOL;

        echo implode($CEL_SEP, [
            '',
            self::pad('Total:', $max_len, ' ', STR_PAD_LEFT),
            self::pad($total[0], 6, ' ', STR_PAD_LEFT),
            self::pad(self::fmtNumber($total[1] / 1e3).'ms', $TIME_LENGTH, ' ', STR_PAD_LEFT),
            self::pad(self::fmtNumber($total[2] / 1e3).'ms', $TIME_LENGTH, ' ', STR_PAD_LEFT),
            self::pad('-', 6, ' ', STR_PAD_RIGHT),
            self::pad(self::fmtMem($total[3]), $MEM_LENGTH, ' ', STR_PAD_LEFT),
            self::pad(self::fmtMem($total[4]), $MEM_LENGTH, ' ', STR_PAD_LEFT),
            self::pad('-', 7, ' ', STR_PAD_RIGHT),
            '',
        ]), PHP_EOL;
        echo PHP_EOL;

        $this->assertGreaterThan($total[1]*10, $total[2], 'hQuery should be at least x10 faster than DOMCrawler');

        return $ctx;
    }

    /**
     * @depends test_find
     */
    public function test_body_find($ctx) {
        list($hdoc, $cdoc) = $ctx;
        return $this->test_find([$hdoc->find('body'), $cdoc->filter('body')]);
    }

    // -----------------------------------------------------

}
