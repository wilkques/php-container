<?php

namespace Wilkques\Container\Tests;

use Wilkques\Container\Tests\Fixtures\Dep;

class ResolvingCallbackTest extends TestCase
{
    public function testGlobalResolvingCallbackFiresForEveryResolution()
    {
        $seen = array();

        $this->container->resolving(function ($instance, $c) use (&$seen) {
            $seen[] = get_class($instance);
        });

        $this->container->make(Dep::class);

        $this->assertSame(array(Dep::class), $seen);
    }

    public function testPerAbstractResolvingCallbackFiresOnlyForThatAbstract()
    {
        $seen = array();

        $this->container->resolving(Dep::class, function ($instance, $c) use (&$seen) {
            $seen[] = 'dep';
        });

        $this->container->resolving('SomethingElse', function ($instance, $c) use (&$seen) {
            $seen[] = 'something-else';
        });

        $this->container->make(Dep::class);

        $this->assertSame(array('dep'), $seen);
    }

    public function testAfterResolvingGlobalAndPerAbstract()
    {
        $seen = array();

        $this->container->afterResolving(function ($instance, $c) use (&$seen) {
            $seen[] = 'global-after';
        });

        $this->container->afterResolving(Dep::class, function ($instance, $c) use (&$seen) {
            $seen[] = 'dep-after';
        });

        $this->container->make(Dep::class);

        $this->assertSame(array('global-after', 'dep-after'), $seen);
    }

    public function testResolvingFiresBeforeAfterResolving()
    {
        $order = array();

        $this->container->resolving(function () use (&$order) {
            $order[] = 'resolving';
        });

        $this->container->afterResolving(function () use (&$order) {
            $order[] = 'afterResolving';
        });

        $this->container->make(Dep::class);

        $this->assertSame(array('resolving', 'afterResolving'), $order);
    }
}
