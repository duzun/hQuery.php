<?php

define('TESTS_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR);
define('ROOT_DIR', strtr(dirname(TESTS_DIR), '\\', '/') . '/');

require_once ROOT_DIR . 'autoload.php';
require_once ROOT_DIR . 'vendor/autoload.php';
require_once TESTS_DIR . 'assert.php';

// Register test autoloader
spl_autoload_register(function ($class) {
    // Convert namespace to full file path
    $prefix = 'Tests\\';
    $base_dir = TESTS_DIR;

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
