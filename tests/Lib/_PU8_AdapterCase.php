<?php
namespace Tests\Lib;

use PHPUnit\Framework\TestCase;

abstract class PU_AdapterCase extends TestCase
{
    // Before any test
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (method_exists(get_called_class(), 'mySetUpBeforeClass')) {
            static::mySetUpBeforeClass();
        }
    }

    // After all tests
    public static function tearDownAfterClass(): void
    {
        if (method_exists(get_called_class(), 'myTearDownAfterClass')) {
            static::myTearDownAfterClass();
        }

        parent::tearDownAfterClass();
    }

    // -----------------------------------------------------
    // Before every test
    public function setUp(): void
    {
        if (method_exists($this, 'mySetUp')) {
            $this->mySetUp();
        }

        parent::setUp();
    }

}
