<?php

namespace Wilkques\Container\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Wilkques\Container\Container;

abstract class TestCase extends BaseTestCase
{
    /** @var \Wilkques\Container\Container */
    protected $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetContainerSingleton();

        // Always a *fresh* container instance per test, never the static
        // singleton, so tests can never leak state into one another.
        $this->container = new Container;
    }

    protected function tearDown(): void
    {
        $this->resetContainerSingleton();

        parent::tearDown();
    }

    /**
     * Reset the Container's static singleton between tests.
     *
     * TODO(WP6): use Container::setInstance(null) once it exists.
     */
    protected function resetContainerSingleton()
    {
        if (method_exists(Container::class, 'setInstance')) {
            Container::setInstance(null);

            return;
        }

        $reflection = new \ReflectionClass(Container::class);

        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    /**
     * Read a protected/private property off of an object (or a static
     * property off of a class) via reflection.
     *
     * @param object|string $objectOrClass
     * @param string        $property
     *
     * @return mixed
     */
    protected function peek($objectOrClass, $property)
    {
        $reflection = new \ReflectionClass($objectOrClass);

        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        if (is_object($objectOrClass)) {
            return $prop->getValue($objectOrClass);
        }

        return $prop->getValue();
    }

    /**
     * Invoke a protected/private method on an object via reflection.
     *
     * @param object $object
     * @param string $method
     * @param array  $args
     *
     * @return mixed
     */
    protected function invoke($object, $method, array $args = array())
    {
        $reflection = new \ReflectionClass($object);

        $m = $reflection->getMethod($method);
        $m->setAccessible(true);

        return $m->invokeArgs($object, $args);
    }
}
