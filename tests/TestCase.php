<?php

namespace ZWF\GoogleFontsOptimizer\Tests;

use Brain\Monkey;

/**
 * Abstract base class for all test case implementations.
 *
 * @package ZWF\GoogleFontsOptimizer\Tests
 */
abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * Prepares the test environment before each test.
     *
     * @return void
     */
    protected function setUp()
    {
        parent::setUp();
        Monkey\setUp();
    }

    /**
     * Cleans up the test environment after each test.
     *
     * @return void
     */
    protected function tearDown()
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
