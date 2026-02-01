<?php
namespace Tests\Shared;

use duzun\hQuery;

/**
 * Surrogate class for testing, to access protected attributes of hQuery
 */
class hQueryTestSurrogate extends hQuery
{
    /**
     * @var mixed
     */
    public $class_idx;
}
