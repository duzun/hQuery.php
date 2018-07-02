<?php
function _hQuery_autoloader($class) {
    if ( strncmp($class, 'duzun\\', 6) == 0 ) {
        $fn = realpath(__DIR__ . '/src/' . strtr(substr($class, 5), '\\', DIRECTORY_SEPARATOR) . '.php');
        return include_once $fn;
    }
    return false;
}

return spl_autoload_register('_hQuery_autoloader');
