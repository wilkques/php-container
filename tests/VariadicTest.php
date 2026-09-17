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
        $result = $this->container->make(Collector::class);

        $this->assertCount(1, $result->foos);
        $this->assertInstanceOf(Foo::class, $result->foos[0]);
    }

    public function testContextualGiveArrayFeedsAllVariadicSlots()
    {
        $a = new Foo;
        $b = new Foo;

        $this->container->when(Collector::class)->needs('$foos')->give(array($a, $b));

        $result = $this->container->make(Collector::class);

        $this->assertSame(array($a, $b), $result->foos);
    }

    public function testOverrideArrayFeedsAllVariadicSlots()
    {
        $a = new Foo;
        $b = new Foo;

        $result = $this->container->make(Collector::class, array('foos' => array($a, $b)));

        $this->assertSame(array($a, $b), $result->foos);
    }

    /** An untyped variadic with no override should resolve to an empty array, not error. */
    public function testUntypedVariadicWithNoOverrideIsEmptyArray()
    {
        $result = $this->container->call(function (...$things) {
            return $things;
        });

        $this->assertSame(array(), $result);
    }
}
