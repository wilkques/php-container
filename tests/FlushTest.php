<?php

namespace Wilkques\Container\Tests;

use Wilkques\Container\Container;
use Wilkques\Container\Tests\Fixtures\Dep;

/**
 * C12
 */
class FlushTest extends TestCase
{
    public function testFlushReturnsThis()
    {
        $result = $this->container->flush();

        $this->assertSame($this->container, $result);
    }

    public function testContainerStillResolvesItselfAfterFlush()
    {
        $this->container->flush();

        $resolved = $this->container->make(Container::class);

        $this->assertSame($this->container, $resolved);
    }

    public function testScopedCalledThriceLeavesExactlyOneEntry()
    {
        $this->container->scoped('a', function () {
            return new Dep;
        });

        $this->container->scoped('a', function () {
            return new Dep;
        });

        $this->container->scoped('a', function () {
            return new Dep;
        });

        $scopedInstances = $this->peek($this->container, 'scopedInstances');

        $this->assertCount(1, $scopedInstances);
    }
}
