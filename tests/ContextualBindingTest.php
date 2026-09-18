<?php

namespace Wilkques\Container\Tests;

use Wilkques\Container\Tests\Fixtures\ConcreteImplementation;
use Wilkques\Container\Tests\Fixtures\ContractInterface;
use Wilkques\Container\Tests\Fixtures\Dep;
use Wilkques\Container\Tests\Fixtures\Partial;

/**
 * N2, N3
 */
class ContextualBindingTest extends TestCase
{
    /** Baseline (already-working) contextual binding by parameter name. */
    public function testContextualBindingByParamName()
    {
        $this->container->when('Wilkques\Container\Tests\Fixtures\Partial')->needs('$b')->give(138);

        $result = $this->container->make('Wilkques\Container\Tests\Fixtures\Partial');

        $this->assertSame(138, $result->b);
    }

    /**
     * N2: contextual binding keyed by TYPE NAME (not '$paramName') must also
     * be honoured — this currently cannot work at all, since
     * getContextualConcrete()/fireTypeArgument() only ever look up
     * '$' . paramName.
     */
    public function testContextualBindingByTypeName()
    {
        $specific = new Dep;

        $this->container->when('Wilkques\Container\Tests\Fixtures\Partial')->needs('Wilkques\Container\Tests\Fixtures\Dep')->give($specific);

        $result = $this->container->make('Wilkques\Container\Tests\Fixtures\Partial', array('b' => 'ignored-here'));

        $this->assertSame($specific, $result->a);
    }

    /**
     * N3: give(null) must be honoured as an explicit contextual value, not
     * treated as "no contextual binding exists" (a null sentinel makes
     * this impossible today).
     */
    public function testContextualGiveNull()
    {
        $this->container->when('Wilkques\Container\Tests\Fixtures\Partial')->needs('$b')->give(null);

        $result = $this->container->make('Wilkques\Container\Tests\Fixtures\Partial');

        $this->assertNull($result->b);
    }

    /** N3: give(0) must be honoured. */
    public function testContextualGiveZero()
    {
        $this->container->when('Wilkques\Container\Tests\Fixtures\Partial')->needs('$b')->give(0);

        $result = $this->container->make('Wilkques\Container\Tests\Fixtures\Partial');

        $this->assertSame(0, $result->b);
    }

    /** N3: give('') must be honoured. */
    public function testContextualGiveEmptyString()
    {
        $this->container->when('Wilkques\Container\Tests\Fixtures\Partial')->needs('$b')->give('');

        $result = $this->container->make('Wilkques\Container\Tests\Fixtures\Partial');

        $this->assertSame('', $result->b);
    }

    /** give(SomeClass::class) resolves the class through the container. */
    public function testContextualGiveClassNameResolvesTheClass()
    {
        $this->container->bind('Wilkques\Container\Tests\Fixtures\ContractInterface', 'Wilkques\Container\Tests\Fixtures\ConcreteImplementation');

        $this->container->when('Wilkques\Container\Tests\Fixtures\Partial')->needs('$b')->give('Wilkques\Container\Tests\Fixtures\ContractInterface');

        $result = $this->container->make('Wilkques\Container\Tests\Fixtures\Partial');

        $this->assertInstanceOf('Wilkques\Container\Tests\Fixtures\ConcreteImplementation', $result->b);
    }
}
