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
                throw new Exception('gzdecode() doesn\'t exist');
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
// PHP < 5.4.0
if(!function_exists('gzdecode') && function_exists('gzinflate')) {
    function gzdecode($data) {
      $len = strlen($data);
      if ($len < 18 || strncmp($data,"\x1F\x8B", 2)) {
        return null;  // Not GZIP format (See RFC 1952)
      }
      $method = ord(substr($data,2,1));  // Compression method
      $flags  = ord(substr($data,3,1));  // Flags
      if (($flags & 31) != $flags) {
        // Reserved bits are set -- NOT ALLOWED by RFC 1952
        return null;
      }
      // NOTE: $mtime may be negative (PHP integer limitations)
      $mtime = unpack("V", substr($data,4,4)) and
      $mtime = $mtime[1];
      $xfl   = substr($data,8,1);
      $os    = substr($data,8,1);
      $headerlen = 10;
      $extralen  = 0;
      $extra     = "";
      if ($flags & 4) {
        // 2-byte length prefixed EXTRA data in header
        if ($len - $headerlen - 2 < 8) {
          return false;    // Invalid format
        }
        $extralen = unpack("v",substr($data,8,2));
        $extralen = $extralen[1];
        if ($len - $headerlen - 2 - $extralen < 8) {
          return false;    // Invalid format
        }
        $extra = substr($data,10,$extralen);
        $headerlen += 2 + $extralen;
      }

      $filenamelen = 0;
      $filename = "";
      if ($flags & 8) {
        // C-style string file NAME data in header
        if ($len - $headerlen - 1 < 8) {
          return false;    // Invalid format
        }
        $filenamelen = strpos(substr($data,8+$extralen),chr(0));
        if ($filenamelen === false || $len - $headerlen - $filenamelen - 1 < 8) {
          return false;    // Invalid format
        }
        $filename = substr($data,$headerlen,$filenamelen);
        $headerlen += $filenamelen + 1;
      }

      $commentlen = 0;
      $comment = "";
      if ($flags & 16) {
        // C-style string COMMENT data in header
        if ($len - $headerlen - 1 < 8) {
          return false;    // Invalid format
        }
        $commentlen = strpos(substr($data,8+$extralen+$filenamelen),chr(0));
        if ($commentlen === false || $len - $headerlen - $commentlen - 1 < 8) {
          return false;    // Invalid header format
        }
        $comment = substr($data,$headerlen,$commentlen);
        $headerlen += $commentlen + 1;
      }

      $headercrc = '';
      if ($flags & 1) {
        // 2-bytes (lowest order) of CRC32 on header present
        if ($len - $headerlen - 2 < 8) {
          return false;    // Invalid format
        }
        $calccrc = crc32(substr($data,0,$headerlen)) & 0xffff;
        $headercrc = unpack('v', substr($data,$headerlen,2));
        $headercrc = $headercrc[1];
        if ($headercrc != $calccrc) {
          return false;    // Bad header CRC
        }
        $headerlen += 2;
      }

      // GZIP FOOTER - These be negative due to PHP's limitations
      $datacrc = unpack('V',substr($data,-8,4)) and
      $datacrc = $datacrc[1];
      $isize = unpack('V',substr($data,-4)) and
      $isize = $isize[1];

      // Perform the decompression:
      $bodylen = $len-$headerlen-8;
      if ($bodylen < 1) {
        // This should never happen - IMPLEMENTATION BUG!
        return null;
      }
      $body = substr($data,$headerlen,$bodylen);
      $data = "";
      if ($bodylen > 0) {
        switch ($method) {
          case 8:
            // Currently the only supported compression method:
            $data = gzinflate($body);
            break;
          default:
            // Unknown compression method
            return false;
        }
      }
      else {
        // I'm not sure if zero-byte body content is allowed.
        // Allow it for now...  Do nothing...
      }

      // Verifiy decompressed size and CRC32:
      // NOTE: This may fail with large data sizes depending on how
      //       PHP's integer limitations affect strlen() since $isize
      //       may be negative for large sizes.
      if ($isize != strlen($data) || crc32($data) != $datacrc) {
        // Bad format!  Length or CRC doesn't match!
        return false;
      }
      return $data;
    }
}
// -----------------------------------------------------
// Delete the temp test user after all tests have fired
register_shutdown_function('PHPUnit_BaseClass::deleteTestData');
// -----------------------------------------------------
?>