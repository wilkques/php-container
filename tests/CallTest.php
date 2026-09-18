<?php

namespace Wilkques\Container\Tests;

use Wilkques\Container\Tests\Fixtures\Counter;
use Wilkques\Container\Tests\Fixtures\Dep;
use Wilkques\Container\Tests\Fixtures\Invokable;

/**
 * B4, N5
 */
class CallTest extends TestCase
{
    protected function additionalSetUp()
    {
        Counter::$count = 0;
        Invokable::$calls = 0;
    }

    /**
     * B4: calling a method on an already-constructed object passed directly
     * into call() must operate on THAT object — it must not re-resolve/
     * reconstruct it from the container, even if a singleton of the same
     * class is registered.
     */
    public function testCallOnPassedInstanceUsesThatExactInstanceNotTheContainerSingleton()
    {
        $singleton = new Counter;

        $this->container->instance(Counter::class, $singleton);

        $countBeforeCall = Counter::$count;

        $passed = new Counter;

        $hash = $this->container->call(array($passed, 'whoAmI'));

        $this->assertSame($passed->whoAmI(), $hash);
        $this->assertSame($singleton, $this->container->make(Counter::class));
        $this->assertSame($countBeforeCall + 1, Counter::$count, 'only $passed should have been constructed by the test itself, call() must not construct another');
    }

    /** N5: calling a static method by [Class, method] must not construct an instance. */
    public function testCallStaticMethodByArrayDoesNotConstructInstance()
    {
        $before = Counter::$count;

        $result = $this->container->call(array(Counter::class, 'staticWhoAmI'));

        $this->assertSame('static-result', $result);
        $this->assertSame($before, Counter::$count, 'calling a static method must not construct a Counter instance');
    }

    /** N5: 'Class@method' string form. */
    public function testCallAtSignStringForm()
    {
        $result = $this->container->call(Counter::class . '@staticWhoAmI');

        $this->assertSame('static-result', $result);
    }

    /** N5: 'Class::method' string form. */
    public function testCallDoubleColonStringForm()
    {
        $result = $this->container->call(Counter::class . '::staticWhoAmI');

        $this->assertSame('static-result', $result);
    }

    public function testClosureWithTypeHintedParamIsAutowired()
    {
        $result = $this->container->call(function (Dep $dep) {
            return $dep;
        });

        $this->assertInstanceOf(Dep::class, $result);
    }

    public function testInvokableObjectIsCallable()
    {
        $invokable = new Invokable;

        $result = $this->container->call($invokable);

        $this->assertSame(1, $result);
    }
}
