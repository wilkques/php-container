<?php

namespace Wilkques\Container\Tests;

use Wilkques\Container\Tests\Fixtures\Dep;

/**
 * tag() / tagged() — tagged() must return an array, never a generator,
 * since this library targets PHP 5.3 (no generator support).
 */
class TagTest extends TestCase
{
    public function testTaggedReturnsArrayOfResolvedInstances()
    {
        $this->container->bind('one', function () {
            return new Dep;
        });

        $this->container->bind('two', function () {
            return new Dep;
        });

        $this->container->tag(array('one', 'two'), 'deps');

        $tagged = $this->container->tagged('deps');

        $this->assertIsArray($tagged, 'tagged() must return a plain array, not a Generator');
        $this->assertCount(2, $tagged);

        foreach ($tagged as $item) {
            $this->assertInstanceOf(Dep::class, $item);
        }
    }

    public function testTaggedWithUnknownTagReturnsEmptyArray()
    {
        $tagged = $this->container->tagged('nope');

        $this->assertIsArray($tagged);
        $this->assertCount(0, $tagged);
    }
}
