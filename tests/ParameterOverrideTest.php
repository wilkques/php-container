<?php

namespace Wilkques\Container\Tests;

use Wilkques\Container\Tests\Fixtures\Dep;
use Wilkques\Container\Tests\Fixtures\Partial;

/**
 * B6
 */
class ParameterOverrideTest extends TestCase
{
    /** The headline case: positional override for the 2nd ctor param, 1st still autowired. */
    public function testPositionalOverrideLeavesOtherParamsAutowired()
    {
        $result = $this->container->make('Wilkques\Container\Tests\Fixtures\Partial', array(1 => 'bee'));

        $this->assertInstanceOf('Wilkques\Container\Tests\Fixtures\Dep', $result->a);
        $this->assertSame('bee', $result->b);
    }

    public function testNamedOnlyOverride()
    {
        $result = $this->container->make('Wilkques\Container\Tests\Fixtures\Partial', array('b' => 'bee'));

        $this->assertInstanceOf('Wilkques\Container\Tests\Fixtures\Dep', $result->a);
        $this->assertSame('bee', $result->b);
    }

    public function testMixedPositionalAndNamedOverride()
    {
        $dep = new Dep;

        $result = $this->container->make('Wilkques\Container\Tests\Fixtures\Partial', array(0 => $dep, 'b' => 'bee'));

        $this->assertSame($dep, $result->a);
        $this->assertSame('bee', $result->b);
    }

    /**
     * An override key that matches no parameter must be ignored, not
     * appended as a stray positional argument. Since $b is untyped with no
     * default and is *not* covered by a real override here, resolution
     * must fail cleanly (see AutowiringTest for the untyped-no-default
     * contract) rather than silently receiving the stray 'nope' value.
     */
    public function testUnknownOverrideKeyIsIgnored()
    {
        $this->expectExceptionCompat('Wilkques\Container\Exceptions\BindingResolutionException');

        $this->container->make('Wilkques\Container\Tests\Fixtures\Partial', array('nonexistent' => 'nope'));
    }
}
