<?php

define('TESTS_DIR', __DIR__ . DIRECTORY_SEPARATOR);
define('ROOT_DIR', strtr(dirname(TESTS_DIR), '\\', '/') . '/');

// define('PHP_IS_NEW', version_compare(PHP_VERSION, '5.3.0') >= 0);
// if ( PHP_IS_NEW ) {
require_once ROOT_DIR . 'autoload.php';
require_once ROOT_DIR . 'vendor/autoload.php';
// }
// else {
//     require_once ROOT_DIR . 'hquery.php';
// }

require_once TESTS_DIR . 'Lib/assert.php';

// Register test autoloader
spl_autoload_register(function ($class) {

    // Special classes:
    switch($class) {
        case 'PHPUnit_Framework_TestCase':
            return class_alias('PHPUnit\Framework\TestCase', 'PHPUnit_Framework_TestCase');

        case 'PHPUnit_Runner_Version':
            return class_alias('PHPUnit\Runner\Version', 'PHPUnit_Runner_Version');

        case 'Tests\Lib\PU_AdapterCase':
        case 'PU_AdapterCase':
            // We have to make some adjustments for PHPUnit_BaseClass to work with
            // PHPUnit 8.0 and still keep backward compatibility
            if (version_compare(PHPUnit_Runner_Version::id(), '8.0.0') >= 0) {
                return require TESTS_DIR . 'Lib/_PU8_AdapterCase.php';
            } else {
                return require TESTS_DIR . 'Lib/_PU7_AdapterCase.php';
            }
    }

    // Convert namespace to full file path
    $prefix = 'Tests\\';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = TESTS_DIR . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
