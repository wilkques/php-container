<?php

namespace Wilkques\Container\Tests\Fixtures;

/**
 * Used to assert that a nested resolution failure reports the build chain,
 * e.g. "[ChainA] -> [ChainB]" — ChainA is buildable, but it depends on
 * ChainB, which is not (see ChainB).
 */
class ChainA
{
    public function __construct(ChainB $b)
    {
    }
}
