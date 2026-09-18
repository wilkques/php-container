<?php

namespace Wilkques\Container\Tests;

use Wilkques\Container\Tests\Fixtures\ConcreteImplementation;
use Wilkques\Container\Tests\Fixtures\ContractInterface;
use Wilkques\Container\Tests\Fixtures\Dep;
use Wilkques\Container\Tests\Fixtures\Invokable;
use Wilkques\Container\Tests\Fixtures\NestedImpl;

/**
 * A1, A3, B5, N1
 */
class BindingTest extends TestCase
{
    /** A1: factory closure must not be invoked during bind(). */
    public function testFactoryClosureIsNotInvokedDuringBind()
    {
        $called = 0;

        $this->container->bind('thing', function () use (&$called) {
            $called++;

            return new Dep;
        });

        $this->assertSame(0, $called, 'bind() must not eagerly invoke the factory');

        $this->container->make('thing');

        $this->assertSame(1, $called, 'make() must invoke the factory exactly once');
    }

    /** A1: non-shared make() invokes the factory every time and returns distinct objects. */
    public function testNonSharedMakeInvokesFactoryEachTime()
    {
        $called = 0;

        $this->container->bind('thing', function () use (&$called) {
            $called++;

            return new Dep;
        });

        $one = $this->container->make('thing');
        $two = $this->container->make('thing');

        $this->assertSame(2, $called);
        $this->assertNotSame($one, $two);
    }

    /** A1: registration order must not matter — A's factory may reference B before B is bound. */
    public function testBindingOrderIsInsensitive()
    {
        $this->container->bind('A', function ($c) {
            return $c->make('B');
        });

        $this->container->bind('B', function () {
            return new Dep;
        });

        $this->assertInstanceOf('Wilkques\Container\Tests\Fixtures\Dep', $this->container->make('A'));
    }

    /** A3: interface bound to a concrete class resolves to that concrete class. */
    public function testInterfaceBoundToConcreteResolves()
    {
        $this->container->bind('Wilkques\Container\Tests\Fixtures\ContractInterface', 'Wilkques\Container\Tests\Fixtures\ConcreteImplementation');

        $resolved = $this->container->make('Wilkques\Container\Tests\Fixtures\ContractInterface');

        $this->assertInstanceOf('Wilkques\Container\Tests\Fixtures\ConcreteImplementation', $resolved);
    }

    /** A3: a class type-hinting the interface receives the bound concrete implementation. */
    public function testTypeHintedInterfaceIsAutowiredFromBinding()
    {
        if (PHP_VERSION_ID < 70100) {
            $this->markTestSkipped('Fixtures\\Php71\\NullableInterfaceCollaborator uses a nullable class type hint, which requires PHP 7.1');
        }

        $this->container->bind('Wilkques\Container\Tests\Fixtures\ContractInterface', 'Wilkques\Container\Tests\Fixtures\ConcreteImplementation');

        $resolved = $this->container->make('Wilkques\Container\Tests\Fixtures\Php71\NullableInterfaceCollaborator');

        $this->assertInstanceOf('Wilkques\Container\Tests\Fixtures\ConcreteImplementation', $resolved->dep);
    }

    /** A3: bind(Iface, NestedImpl) where NestedImpl itself has a ctor dependency resolves recursively. */
    public function testInterfaceBoundToImplementationWithItsOwnDependencyResolvesRecursively()
    {
        $this->container->bind('Wilkques\Container\Tests\Fixtures\ContractInterface', 'Wilkques\Container\Tests\Fixtures\NestedImpl');

        $resolved = $this->container->make('Wilkques\Container\Tests\Fixtures\ContractInterface');

        $this->assertInstanceOf('Wilkques\Container\Tests\Fixtures\NestedImpl', $resolved);
        $this->assertInstanceOf('Wilkques\Container\Tests\Fixtures\Dep', $resolved->dep);
    }

    /** B5: binding an invokable object as the concrete must not call it during bind(). */
    public function testBindingInvokableObjectDoesNotCallItDuringBind()
    {
        Invokable::$calls = 0;

        $invokable = new Invokable;

        $this->container->bind('k', $invokable);

        $this->assertSame(0, Invokable::$calls, 'bind() must not call() an invokable concrete');
    }

    /** N1: register() with a batch array must not TypeError, and must store the given objects. */
    public function testRegisterArrayOfPairsDoesNotThrowAndStoresObjects()
    {
        $objA = new Dep;
        $objB = new Dep;

        $this->container->register(array(
            array('a', $objA),
            array('b', $objB),
        ));

        $this->assertSame($objA, $this->container->get('a'));
        $this->assertSame($objB, $this->container->get('b'));
    }
}
