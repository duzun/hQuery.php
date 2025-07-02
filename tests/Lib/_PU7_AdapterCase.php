<?php
namespace Tests\Lib;

abstract class PU_AdapterCase extends \PHPUnit_Framework_TestCase
{
    // Before any test
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        if (method_exists(get_called_class(), 'mySetUpBeforeClass')) {
            static::mySetUpBeforeClass();
        }
    }

    // After all tests
    public static function tearDownAfterClass()
    {
        if (method_exists(get_called_class(), 'myTearDownAfterClass')) {
            static::myTearDownAfterClass();
        }

        parent::tearDownAfterClass();
    }

    // -----------------------------------------------------
    // Before every test
    public function setUp()
    {
        if (method_exists($this, 'mySetUp')) {
            $this->mySetUp();
        }

        parent::setUp();
    }

}
