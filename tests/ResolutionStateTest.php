<?php

namespace Wilkques\Container\Tests;

use Wilkques\Container\Tests\Fixtures\ThrowingCtor;

/**
 * R3 — PHP-5.3-has-no-`finally` hazard: if the container pushes onto
 * $buildStack / $with before building and something throws mid-build, those
 * arrays must still be cleaned back up (e.g. via a manual try/catch that
 * re-throws after popping, since `finally` isn't available on PHP 5.3).
 */
class ResolutionStateTest extends TestCase
{
    public function testBuildStackAndWithAreClearedAfterAThrowingBuild()
    {
        try {
            $this->container->make(ThrowingCtor::class);
        } catch (\Exception $e) {
            // expected
        }

        $buildStack = $this->peek($this->container, 'buildStack');
        $with = $this->peek($this->container, 'with');

        $this->assertSame(array(), $buildStack, 'buildStack must be unwound after a throwing build');
        $this->assertSame(array(), $with, '$with must be unwound after a throwing build');
    }
}
