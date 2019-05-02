<?php
use duzun\hQuery;

// -----------------------------------------------------
/**
 *  @author Dumitru Uzun (DUzun.Me)
 */
// -----------------------------------------------------
define('PHPUNIT_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR);
define('ROOT_DIR', strtr(dirname(PHPUNIT_DIR), '\\', '/') . '/');
if (!class_exists('PHPUnit_Runner_Version')) {
    class_alias('PHPUnit\Runner\Version', 'PHPUnit_Runner_Version');
}

// define('PHP_IS_NEW', version_compare(PHP_VERSION, '5.3.0') >= 0);
// -----------------------------------------------------
// if ( PHP_IS_NEW ) {
require_once ROOT_DIR . 'autoload.php';
require_once ROOT_DIR . 'vendor/autoload.php';
// }
// else {
//     require_once ROOT_DIR . 'hquery.php';
// }
// -----------------------------------------------------

// We have to make some adjustments for PHPUnit_BaseClass to work with
// PHPUnit 8.0 and still keep backward compatibility
if (version_compare(PHPUnit_Runner_Version::id(), '8.0.0') >= 0) {
    require_once PHPUNIT_DIR . '_PU8_TestCase.php';
} else {
    require_once PHPUNIT_DIR . '_PU7_TestCase.php';
}

// -----------------------------------------------------
// -----------------------------------------------------
/**
 * @backupGlobals disabled
 */
// -----------------------------------------------------
abstract class PHPUnit_BaseClass extends PU_TestCase
{
    /**
     * @var boolean
     */
    public static $log = true;

    /**
     * @var string
     */
    public static $testName;

    /**
     * @var string
     */
    public static $className;

    // -----------------------------------------------------
    // Before every test
    public function mySetUp()
    {
        self::$testName  = $this->getName();
        self::$className = get_class($this);

        // parent::mySetUp();
    }

    // -----------------------------------------------------
    /**
     * Asserts that a method exists.
     *
     * @param  string                                   $methodName
     * @param  string|object                            $className
     * @throws PHPUnit_Framework_AssertionFailedError
     */
    public static function assertMehodExists($methodName, $className, $message = '')
    {
        self::assertThat(method_exists($className, $methodName), self::isTrue(), $message);
    }

    // Alias to $this->assertMehodExists()
    public function assertClassHasMethod()
    {
        $args = func_get_args();
        return call_user_func_array([$this, 'assertMehodExists'], $args);
    }

    // -----------------------------------------------------
    // Helper methods:

    /**
     * Log a message to console
     */
    public static function log()
    {
        if (empty(self::$log)) {
            return;
        }

        /**
         * @var int
         */
        static $idx = 0;
        /**
         * @var mixed
         */
        static $lastTest;
        /**
         * @var mixed
         */
        static $lastClass;
        if ($lastTest != self::$testName || $lastClass != self::$className) {
            echo PHP_EOL, PHP_EOL, '-> ', self::$className . '::' . self::$testName, ' ()';
            $lastTest  = self::$testName;
            $lastClass = self::$className;
        }
        $args = func_get_args();
        foreach ($args as $k => $v) {
            is_string($v) or is_int($v) or is_float($v) or $args[$k] = var_export($v, true);
        }

        echo PHP_EOL
        , ''
        , str_pad(++$idx, 3, ' ', STR_PAD_LEFT)
        , ")\t"
        , implode(' ', $args)
        , PHP_EOL
        ;
    }

    // -----------------------------------------------------
    public static function deleteTestData() {}

    // -----------------------------------------------------
    /**
     * @param  string $fn  filename
     * @return string file contents or false
     */
    public static function file_get_contents($fn)
    {
        $ffn = self::file_exists($fn);
        return $ffn ? file_get_contents($ffn) : false;
    }

    /**
     * @param  string    $fn filename
     * @return boolean
     */
    public static function file_exists($fn)
    {
        $ffn = PHPUNIT_DIR . $fn;
        if (!file_exists($ffn)) {
            $zfn = $ffn . '.gz';
            if (!file_exists($zfn)) {
                return false;
            }
            $gz   = file_get_contents($zfn);
            $data = hQuery::gzdecode($gz);
            if (!file_put_contents($ffn, $data)) {
                return false;
            }

        }
        return $ffn;
    }

    /**
     * @param  string   $fn filename
     * @return string
     */
    public static function fn($fn)
    {
        $ret = self::file_exists($fn) or $ret = $fn;
        return $ret;
    }

    // -----------------------------------------------------
}
// -----------------------------------------------------
// Delete the temp test user after all tests have fired
register_shutdown_function('PHPUnit_BaseClass::deleteTestData');
// -----------------------------------------------------
