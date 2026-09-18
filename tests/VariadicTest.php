<?php

namespace Wilkques\Container\Tests;

use Wilkques\Container\Tests\Fixtures\Collector;
use Wilkques\Container\Tests\Fixtures\Foo;

/**
 * C11
 */
class VariadicTest extends TestCase
{
    public function testNoContextualYieldsOneAutowiredInstance()
    {
        if (PHP_VERSION_ID < 50600) {
            $this->markTestSkipped('Fixtures\\Collector uses a variadic ctor param, which requires PHP 5.6');
        }

        $result = $this->container->make('Wilkques\Container\Tests\Fixtures\Collector');

        $this->assertCount(1, $result->foos);
        $this->assertInstanceOf('Wilkques\Container\Tests\Fixtures\Foo', $result->foos[0]);
    }

    public function testContextualGiveArrayFeedsAllVariadicSlots()
    {
        if (PHP_VERSION_ID < 50600) {
            $this->markTestSkipped('Fixtures\\Collector uses a variadic ctor param, which requires PHP 5.6');
        }

        $a = new Foo;
        $b = new Foo;

        $this->container->when('Wilkques\Container\Tests\Fixtures\Collector')->needs('$foos')->give(array($a, $b));

        $result = $this->container->make('Wilkques\Container\Tests\Fixtures\Collector');

        $this->assertSame(array($a, $b), $result->foos);
    }

    public function testOverrideArrayFeedsAllVariadicSlots()
    {
        if (PHP_VERSION_ID < 50600) {
            $this->markTestSkipped('Fixtures\\Collector uses a variadic ctor param, which requires PHP 5.6');
        }

        $a = new Foo;
        $b = new Foo;

        $result = $this->container->make('Wilkques\Container\Tests\Fixtures\Collector', array('foos' => array($a, $b)));

        $this->assertSame(array($a, $b), $result->foos);
    }

    /** An untyped variadic with no override should resolve to an empty array, not error. */
    public function testUntypedVariadicWithNoOverrideIsEmptyArray()
    {
        if (PHP_VERSION_ID < 50600) {
            $this->markTestSkipped('Variadic params require PHP 5.6');
        }

        $result = $this->container->call(\Wilkques\Container\Tests\Fixtures\Php56\UntypedVariadicCallable::get());

        $this->assertSame(array(), $result);
    }
}
