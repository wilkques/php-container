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
        $this->container->when(Partial::class)->needs('$b')->give(138);

        $result = $this->container->make(Partial::class);

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

        $this->container->when(Partial::class)->needs(Dep::class)->give($specific);

        $result = $this->container->make(Partial::class, array('b' => 'ignored-here'));

        $this->assertSame($specific, $result->a);
    }

    /**
     * N3: give(null) must be honoured as an explicit contextual value, not
     * treated as "no contextual binding exists" (a null sentinel makes
     * this impossible today).
     */
    public function testContextualGiveNull()
    {
        $this->container->when(Partial::class)->needs('$b')->give(null);

        $result = $this->container->make(Partial::class);

        $this->assertNull($result->b);
    }

    /** N3: give(0) must be honoured. */
    public function testContextualGiveZero()
    {
        $this->container->when(Partial::class)->needs('$b')->give(0);

        $result = $this->container->make(Partial::class);

        $this->assertSame(0, $result->b);
    }

    /** N3: give('') must be honoured. */
    public function testContextualGiveEmptyString()
    {
        $this->container->when(Partial::class)->needs('$b')->give('');

        $result = $this->container->make(Partial::class);

        $this->assertSame('', $result->b);
    }

    /** give(SomeClass::class) resolves the class through the container. */
    public function testContextualGiveClassNameResolvesTheClass()
    {
        $this->container->bind(ContractInterface::class, ConcreteImplementation::class);

        $this->container->when(Partial::class)->needs('$b')->give(ContractInterface::class);

        $result = $this->container->make(Partial::class);

        $this->assertInstanceOf(ConcreteImplementation::class, $result->b);
    }
}
