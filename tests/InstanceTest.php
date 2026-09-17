<?php

namespace Wilkques\Container\Tests;

use Wilkques\Container\Tests\Fixtures\Dep;

/**
 * A2
 */
class InstanceTest extends TestCase
{
    public function testNonSharedMakeReturnsDistinctObjectsAndDoesNotCacheInstance()
    {
        $this->container->bind('thing', function () {
            return new Dep;
        });

        $one = $this->container->make('thing');
        $two = $this->container->make('thing');

        $this->assertNotSame($one, $two);

        $instances = $this->peek($this->container, 'instances');

        $this->assertArrayNotHasKey('thing', $instances);
    }

    public function testSingletonReturnsSameObjectAndCachesInstance()
    {
        $this->container->singleton('thing', function () {
            return new Dep;
        });

        $one = $this->container->make('thing');
        $two = $this->container->make('thing');

        $this->assertSame($one, $two);

        $instances = $this->peek($this->container, 'instances');

        $this->assertArrayHasKey('thing', $instances);
    }

    /** Repeated non-shared make() calls must not leak entries into $instances. */
    public function testNonSharedMakeDoesNotLeakInstancesOverManyCalls()
    {
        $this->container->bind('thing', function () {
            return new Dep;
        });

        for ($i = 0; $i < 1000; $i++) {
            $this->container->make('thing');
        }

        $instances = $this->peek($this->container, 'instances');

        // Only the container binds itself into $instances by default (see
        // Container::__construct), so a non-shared binding should never add
        // to that count no matter how many times it is resolved.
        $this->assertLessThanOrEqual(2, count($instances));
        $this->assertArrayNotHasKey('thing', $instances);
    }
}
