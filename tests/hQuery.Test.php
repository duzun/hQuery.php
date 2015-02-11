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
    public function testFind() {
        // 1)
        $a = self::$inst->find('.test-class #test-div.test-div > a');

        $this->assertNotEmpty($a);
        $this->assertTrue($a instanceof HTML_Node);
        $this->assertEquals('a', $a->nodeName);
        $this->assertEquals('This is a link', $a->text);
        $this->assertEquals('https://DUzun.Me/path', $a->attr('href'));
        $this->assertEquals('div', $a->parent->nodeName);
        $this->assertEquals('test-div', $a->parent->attr('id'));

        // 2)
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