<?php

namespace Wilkques\Container\Tests;

use Wilkques\Container\Tests\Fixtures\Dep;

/**
 * C10
 */
class AliasTest extends TestCase
{
    public function testAliasChainThreeDeepResolvesToOriginalBinding()
    {
        $dep = new Dep;

        $this->container->instance('a', $dep);

        $this->container->alias('a', 'b');
        $this->container->alias('b', 'c');
        $this->container->alias('c', 'd');

        $this->assertSame($dep, $this->container->make('d'));
    }

    public function testSelfAliasThrowsLogicException()
    {
        $this->expectExceptionCompat('LogicException');

        $this->container->alias('a', 'a');
    }
}
