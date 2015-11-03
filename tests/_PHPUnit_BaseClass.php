<?php
// -----------------------------------------------------
/**
 *  @author Dumitru Uzun (DUzun.Me)
 *
 */
// -----------------------------------------------------
define('PHPUNIT_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR);
define('ROOT_DIR', strtr(dirname(PHPUNIT_DIR), '\\', '/').'/');
define('PHP_IS_NEW', version_compare(PHP_VERSION, '5.3.0') >= 0);
// -----------------------------------------------------
if ( PHP_IS_NEW ) {
    function _hQuery_Test_autoloader($class) {
        if ( strncmp($class, 'duzun\\', 6) == 0 ) {
            $fn = realpath(ROOT_DIR .'psr-4' . strtr(substr($class, 5), '\\', DIRECTORY_SEPARATOR) . '.php');
            return include_once $fn;
        }
        return false;
    }
    spl_autoload_register('_hQuery_Test_autoloader');
}
require_once ROOT_DIR . 'hquery.php';
// -----------------------------------------------------
// -----------------------------------------------------
/**
 * @backupGlobals disabled
 */
// -----------------------------------------------------
abstract class PHPUnit_BaseClass extends PHPUnit_Framework_TestCase {
    public static $log = true;
    public static $testName;
    public static $className;
    // -----------------------------------------------------
    // Before any test
    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();
    }
    // After all tests
    public static function tearDownAfterClass() {
        parent::tearDownAfterClass();
    }

    // -----------------------------------------------------
    // Before every test
    public function setUp() {
        self::$testName = $this->getName();
        self::$className = get_class($this);

        parent::setUp();
    }
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

    // Alias to $this->assertMehodExists()
    public function assertClassHasMethod() {
        $args = func_get_args();
        return call_user_func_array(array($this, 'assertMehodExists'), $args);
    }

    // -----------------------------------------------------
    // Helper methods:

    public static function log() {
        if ( empty(self::$log) ) return;
        static $idx = 0;
        static $lastTest;
        static $lastClass;
        if ( $lastTest != self::$testName || $lastClass != self::$className ) {
            echo PHP_EOL, PHP_EOL, '-> ', self::$className.'::'.self::$testName, ' ()';
            $lastTest  = self::$testName;
            $lastClass = self::$className;
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
    public static function deleteTestData() {
    }
    // -----------------------------------------------------
    public static function file_get_contents($fn) {
        $ffn = self::file_exists($fn);
        return $ffn ? file_get_contents($ffn) : false;
    }
    
    public static function file_exists($fn) {
        $ffn = PHPUNIT_DIR . $fn;
        if ( !file_exists($ffn) ) {
            $zfn = $ffn . '.gz';
            if ( !file_exists($zfn) ) {
                return false;
            }
            $gz = file_get_contents($zfn);
            if ( function_exists('gzdecode') ) {
                $data = gzdecode($gz);
            }
            else {
                $data = gzinflate(substr($gz, 10, -8));
            }
            if ( !file_put_contents($ffn, $data) ) return false;
        }
        return $ffn;
    }
    
    public static function fn($fn) {
        return self::file_exists($fn) ?: $fn;
    }
    
    
    // -----------------------------------------------------

}
// -----------------------------------------------------
// Delete the temp test user after all tests have fired
register_shutdown_function('PHPUnit_BaseClass::deleteTestData');
// -----------------------------------------------------
?>