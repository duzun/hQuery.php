<?php
// ------------------------------------------------------------------------
/**
 * Backward compatibility class aliases.
 *
 *  PHP 5+ classes: (deprecated version)
 *     hQuery
 *     hQuery_Element
 *     hQuery_Node
 *
 *  PHP 5.3+ classes:
 *     duzun\hQuery
 *     duzun\hQuery\Element
 *     duzun\hQuery\Node
 *
 *
 *  API Documentation at https://duzun.github.io/hQuery.php
 *
 *  Copyright (C) 2014-2019 Dumitru Uzun
 *
 *  @author Dumitru Uzun (DUzun.ME)
 *  @license MIT
 */

// ------------------------------------------------------------------------
class_exists('duzun\\hQuery\\Parser'              , false) or require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'hQuery' . DIRECTORY_SEPARATOR . 'Parser.php';
class_exists('duzun\\hQuery\\Parser\\Selector'    , false) or require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'hQuery' . DIRECTORY_SEPARATOR . 'Parser' . DIRECTORY_SEPARATOR . 'Selector.php';
class_exists('duzun\\hQuery\\Parser\\HTMLSelector', false) or require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'hQuery' . DIRECTORY_SEPARATOR . 'Parser' . DIRECTORY_SEPARATOR . 'HTML.php';

class_exists('duzun\\hQuery\\Node'        , false) or require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'hQuery' . DIRECTORY_SEPARATOR . 'Node.php';
class_exists('duzun\\hQuery\\Element'     , false) or require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'hQuery' . DIRECTORY_SEPARATOR . 'Element.php';
class_exists('duzun\\hQuery\\Context'     , false) or require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'hQuery' . DIRECTORY_SEPARATOR . 'Context.php';
class_exists('duzun\\hQuery\\HTML_Index'  , false) or require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'hQuery' . DIRECTORY_SEPARATOR . 'HTML_Index.php';
class_exists('duzun\\hQuery'              , false) or require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'hQuery.php';

class_exists('hQuery_Node'        , false) or class_alias('duzun\\hQuery\\Node'        , 'hQuery_Node');
class_exists('hQuery_Context'     , false) or class_alias('duzun\\hQuery\\Context'     , 'hQuery_Context');
class_exists('hQuery_HTML_Index'  , false) or class_alias('duzun\\hQuery\\HTML_Index'  , 'hQuery_HTML_Index');
class_exists('hQuery_Element'     , false) or class_alias('duzun\\hQuery\\Element'     , 'hQuery_Element');
class_exists('hQuery'             , false) or class_alias('duzun\\hQuery'              , 'hQuery');


// ------------------------------------------------------------------------
