<?php

namespace Wilkques\Container\Tests;

use Wilkques\Container\Tests\Fixtures\Dep;

class ExtendTest extends TestCase
{
    public function testExtendBeforeResolutionWrapsTheBuiltInstance()
    {
        $this->container->bind('thing', function () {
            return new Dep;
        });

        $this->container->extend('thing', function ($instance, $c) {
            $wrapper = new \stdClass;
            $wrapper->inner = $instance;

            return $wrapper;
        });

        $result = $this->container->make('thing');

        $this->assertInstanceOf('stdClass', $result);
        $this->assertInstanceOf('Wilkques\Container\Tests\Fixtures\Dep', $result->inner);
    }

    public function testExtendAfterResolutionOnASingletonAppliesImmediately()
    {
        $this->container->singleton('thing', function () {
            return new Dep;
        });

        // Resolve it once, before extend() is registered.
        $original = $this->container->make('thing');

        $this->container->extend('thing', function ($instance, $c) {
            $wrapper = new \stdClass;
            $wrapper->inner = $instance;

            return $wrapper;
        });

        $result = $this->container->make('thing');

        $this->assertInstanceOf('stdClass', $result);
        $this->assertSame($original, $result->inner);
    }

    public function testTwoExtendersApplyInRegistrationOrder()
    {
        $this->container->bind('thing', function () {
            return array('base');
        });

        $this->container->extend('thing', function ($instance) {
            $instance[] = 'first';

            return $instance;
        });

        $this->container->extend('thing', function ($instance) {
            $instance[] = 'second';

            return $instance;
        });

        $result = $this->container->make('thing');

        $this->assertSame(array('base', 'first', 'second'), $result);
    }
}
