<?php
/**
 * Global PHPUnit assertion functions.
 */

use PHPUnit\Framework\Assert;

// -----------------------------------------------------
// Custom Asserts
// -----------------------------------------------------
/**
 * Asserts that a method exists.
 *
 * @param  string                                   $methodName
 * @param  string|object                            $className
 * @throws PHPUnit_Framework_AssertionFailedError
 */
function assertMehodExists($methodName, $className, $message = '')
{
    return Assert::assertThat(method_exists($className, $methodName), Assert::isTrue(), $message);
}

// -----------------------------------------------------
// PHPUnit Asserts
// -----------------------------------------------------

function assertInstanceOf($expected, $actual, $message = '')
{
    return Assert::assertInstanceOf($expected, $actual, $message);
}


function assertEquals($expected, $actual, $message = '')
{
    return Assert::assertEquals($expected, $actual, $message);
}

function assertNotEquals($expected, $actual, $message = '')
{
    return Assert::assertNotEquals($expected, $actual, $message);
}

function assertTrue($condition, $message = '')
{
    return Assert::assertTrue($condition, $message);
}

function assertFalse($condition, $message = '')
{
    return Assert::assertFalse($condition, $message);
}

function assertNull($actual, $message = '')
{
    return Assert::assertNull($actual, $message);
}

function assertNotNull($actual, $message = '')
{
    return Assert::assertNotNull($actual, $message);
}

function assertEmpty($actual, $message = '')
{
    return Assert::assertEmpty($actual, $message);
}

function assertNotEmpty($actual, $message = '')
{
    return Assert::assertNotEmpty($actual, $message);
}

function assertGreaterThan($expected, $actual, $message = '')
{
    return Assert::assertGreaterThan($expected, $actual, $message);
}

function assertLessThan($expected, $actual, $message = '')
{
    return Assert::assertLessThan($expected, $actual, $message);
}
