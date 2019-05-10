<?php
use duzun\hQuery\Parser;
use duzun\hQuery\Parser\Selector as SelectorParser;
use duzun\hQuery\Parser\HTML as HTMLParser;

// -----------------------------------------------------
/**
 *  @TODO: Test all methods
 *  @author DUzun.Me
 */
// -----------------------------------------------------
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '_PHPUnit_BaseClass.php';

// -----------------------------------------------------
// Surogate class for testing, to access protected attributes of HTMLParser
class TestHTMLParserTests extends HTMLParser
{
    /**
     * @param $str
     * @param $p
     */
    public static function _findTagClose($str, $p)
    {
        return parent::_findTagClose($str, $p);
    }
}

// -----------------------------------------------------
class TestParser extends PHPUnit_BaseClass
{
    // -----------------------------------------------------
    /**
     * @var TestHQueryTests
     */
    public static $inst;

    /**
     * @var boolean
     */
    public static $log = true;

    // Before any test
    public static function mySetUpBeforeClass()
    {
        self::$inst = new Parser("tn1#id1[attr='[x]'] .cl1.cl2:first tn2:5\t, \n\ttn3.cl3 tn4#id2:eq(-1) > tn5:last-child>tn6:lt('3' ) + span[data-name] ~ a[href]", 9);
    }

    // After all tests
    public static function myTearDownAfterClass()
    {
        self::$inst = null;
    }

    // -----------------------------------------------------
    // -----------------------------------------------------
    /**
     * @return null
     */
    public function _test_play()
    {
        // return;

        $p = self::$inst;
        $n = 100000;

        // Play with readTo():
        // {{{
        $at = strpos($p->s, 'tn4#id2:eq(-1)');
        // $n1 = $p->readTo($p::$spaceRange);

        $t1 = self::timer();
        for ($i = $n; --$i;) {
            $p->i = $at;
            $n1   = $p->readTo('+');
            $i1   = $p->i;
        }
        $e1 = self::timer($t1);

        $t2 = self::timer();
        for ($i = $n; --$i;) {
            $p->i = $at;

            $i2 = $p->i;
            $j  = $p->skipTo('+');
            $n2 = substr($p->s, $i2, $j - $i2);

            // $n2 = $p->readUntil($p::$spaceRange);
            $i2 = $p->i;
        }
        $e2 = self::timer($t2);

        self::log('n1: ' . $n1 . ' in ' . $e1 . ' at ' . $i1);
        self::log('n2: ' . $n2 . ' in ' . $e2 . ' at ' . $i2);

        $this->assertEquals($n1, $n2);
        // }}}
        return;

        // Play with readName():
        // {{{
        $at = strpos($p->s, ':last-child') + 1;

        $t1 = self::timer();
        for ($i = $n; --$i;) {
            $p->i = $at;
            $n1   = $p->readName();
        }
        $e1 = self::timer($t1);

        $t2 = self::timer();
        for ($i = $n; --$i;) {
            $p->i = $at;
            $n2   = $p->readWhile($p::$nameRange);
        }
        $e2 = self::timer($t2);

        self::log('n1', $n1 . ' in ' . $e1);
        self::log('n2', $n2 . ' in ' . $e2);

        $this->assertEquals($n1, $n2);
        // }}}
        return;
    }

    // -----------------------------------------------------
    public function test_SelectorParser()
    {
        $p = self::$inst;
        $a = SelectorParser::exec($p->s);
        $this->assertEquals('tn1', $a[0][0]['n']);
        $this->assertEquals('id1', $a[0][0]['i']);
        $this->assertEquals(array('attr' => '[x]'), $a[0][0]['a']);
        $this->assertTrue(empty($a[0][1]['a']));

        $this->assertEquals('tn3', $a[1][0]['n']);
        $this->assertEquals(array('cl3'), $a[1][0]['c']);

        $this->assertEquals('tn4', $a[1][1]['n']);
        $this->assertEquals('id2', $a[1][1]['i']);
        $this->assertNotEmpty($a[1][1]['p']);

        $this->assertEquals('tn5', $a[1][2]['n']);
        $this->assertEquals('>', $a[1][2]['x']);
        $this->assertNotEmpty($a[1][2]['p']);

        $this->assertEquals('tn6', $a[1][3]['n']);
        // $this->assertNotEmpty($a[1][3]['p']);
        $this->assertEquals(array(array('<' => 3)), $a[1][3]['p']);
    }

    // -----------------------------------------------------
    public function test_static_findTagClose()
    {
        // A string with misplaced quotes inside a tag
        $str1 = '<img class="map>Img" "src"="https://cdn.duzun.lh/images/logo.png"">
                 <div class="overlayLowlightoverlayBottom">abra-kadabra</div>
               ';
        $str2 = '<img "class"="mapImg" title="What <br>a nice day for testing!!!" ">
                 <div class="overlayLowlightoverlayBottom">abra-kadabra</div>
               ';
        $str3 = "<img 'class 4 mapImg' title='What <br>a nice day for testing!!' ''>
                 <div class='overlayLowlightoverlayBottom'>abra-kadabra</div>
               ";

        $r = TestHTMLParserTests::_findTagClose($str1, 1);
        $this->assertEquals(66, $r);

        $r = TestHTMLParserTests::_findTagClose($str2, 1);
        $this->assertEquals(66, $r);

        $r = TestHTMLParserTests::_findTagClose($str3, 1);
        $this->assertEquals(66, $r);
    }

    // -----------------------------------------------------
    public function test_readTo()
    {
        $p    = self::$inst;
        $p->i = strpos($p->s, 'tn3.cl3 tn4#id2') - 3;
        $p->skipWhitespace();

        $n = $p->readTo('#');
        $this->assertEquals('tn3.cl3 tn4', $n);

    }

    // -----------------------------------------------------
    public function test_readName()
    {
        $p = self::$inst;

        $p->i = $i = strpos($p->s, ':last-child') + 1;
        $n1   = $p->readName();
        $this->assertEquals($n1, 'last-child');
        $this->assertEquals($i + 10, $p->i);

        $p->i = $i;
        $n2   = $p->readWhile($p::$nameRange);
        $this->assertEquals($i + 10, $p->i);

        $this->assertEquals($n1, $n2, 'last-child');
    }

    // -----------------------------------------------------
    // -----------------------------------------------------

}
