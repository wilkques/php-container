<?php

namespace Wilkques\Container\Tests\Fixtures;

/**
 * Abstract on purpose — unresolvable, so building ChainA (which depends on
 * this) must fail with a build-chain message like "[ChainA] -> [ChainB]".
 */
abstract class ChainB
{
    abstract public function doSomething();
}
