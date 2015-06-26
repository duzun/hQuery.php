<?php
// -----------------------------------------------------
/**
 *  @author DUzun.Me
 *
 *  @TODO: Test all methods
 */
// -----------------------------------------------------
require_once dirname(dirname(__FILE__)) . '/hquery.php';
// -----------------------------------------------------
// Surogate class for testing, to access protected attributes of hQuery
class TestHQueryTests extends hQuery {
    public $class_idx;

    public static function html_findTagClose($str, $p) {
        return parent::html_findTagClose($str, $p);
    }
}
// -----------------------------------------------------

class TestHQuery extends PHPUnit_Framework_TestCase {
    // -----------------------------------------------------
    static public $inst;
    static public $className = 'hQuery';
    static public $baseUrl   = 'https://DUzun.Me/';
    static public $log       = true;
    static public $testName;

    // Before any test
    static public function setUpBeforeClass() {
        hQuery::$_mockup_class = 'TestHQueryTests';

        self::$inst = TestHQueryTests::fromHTML(
            '<doctype html>'.
            '<html>'.
            '<head>'.
                '<title>Sample HTML Doc</title>'.
            '</head>'.
            '<body class="test-class">'.
                '<div id="test-div" class="test-class test-div">'.
                    '<a href="/path">'.
                        'This is a link'.
                    '</a>'.
                '</div>'.
                'Contents...'.
            '</body>'.
            '</html>'
            , self::$baseUrl
        );
        self::log(get_class(self::$inst));
    }

    // After all tests
    static public function tearDownAfterClass() {
        self::$inst = NULL;
    }


    // Before every test
    public function setUp() {
        self::$testName = $this->getName();
    }

    // After every test
    public function tearDown() {
    }

    // -----------------------------------------------------
    // -----------------------------------------------------

    public function testClass() {
        $this->assertMehodExists('fromHTML', self::$className);
        $this->assertMehodExists('fromFile', self::$className);
        $this->assertMehodExists('fromURL' , self::$className);
    }

    // -----------------------------------------------------
    public function test_static_html_findTagClose() {
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

        $r = TestHQueryTests::html_findTagClose($str1, 1);
        $this->assertEquals(66, $r);

        $r = TestHQueryTests::html_findTagClose($str2, 1);
        $this->assertEquals(66, $r);

        $r = TestHQueryTests::html_findTagClose($str3, 1);
        $this->assertEquals(66, $r);
    }

    // -----------------------------------------------------
    public function testFind() {
        // 1)
        $a = self::$inst->find('.test-class #test-div.test-div > a');

        $this->assertNotEmpty($a);
        $this->assertTrue($a instanceof hQuery_Element);
        $this->assertEquals('a', $a->nodeName);
        $this->assertEquals('This is a link', $a->text);
        $this->assertEquals('https://DUzun.Me/path', $a->attr('href'));
        $this->assertEquals('div', $a->parent->nodeName);
        $this->assertEquals('test-div', $a->parent->attr('id'));

        // 2)
        $ff = TestHQueryTests::fromFile(dirname(__FILE__) . '/data/attr.html');
        $aa = $ff->find('a.aa');
        $this->assertEquals(3, count($aa));
    }

    // -----------------------------------------------------
    public function testHttp_wr() {
        // @TODO
    }

    // -----------------------------------------------------
    // -----------------------------------------------------
    /**
     * Asserts that a method exists.
     *
     * @param  string $methodName
     * @param  string|object  $className
     * @throws PHPUnit_Framework_AssertionFailedError
     */
    public static function assertMehodExists($methodName, $className, $message = '') {
        self::assertThat(method_exists($className, $methodName), self::isTrue(), $message);
    }
    // -----------------------------------------------------
    static function log() {
        if ( empty(self::$log) ) return;
        static $idx = 0;
        static $lastTest;
        if ( $lastTest != self::$testName ) {
            echo PHP_EOL, '-> ', self::$testName, ' ()';
            $lastTest = self::$testName;
        }
        $args = func_get_args();
        foreach($args as $k => $v) is_string($v) or is_int($v) or is_float($v) or $args[$k] = var_export($v, true);
        echo PHP_EOL
            , ""
            , str_pad(++$idx, 3, ' ', STR_PAD_LEFT)
            , ")\t"
            , implode(' ', $args)
        ;
    }
    // -----------------------------------------------------
    // -----------------------------------------------------

}
?>