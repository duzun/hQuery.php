<?php
class_exists('\hQuery', false) or require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'hquery.php';
class_exists('duzun\\hQuery\\Node', false) or class_alias('hQuery_Node', 'duzun\\hQuery\\Node');
?>