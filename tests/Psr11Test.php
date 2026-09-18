<?php

namespace Wilkques\Container\Tests;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Wilkques\Container\Tests\Fixtures\Dep;
use Wilkques\Container\Tests\Fixtures\ThrowingCtor;

class Psr11Test extends TestCase
{
    public function testPsr11ContainerInterfaceIsRegisteredAtBootstrap()
    {
        $this->assertTrue(interface_exists('Psr\Container\ContainerInterface'));
    }

    public function testContainerImplementsPsr11ContainerInterface()
    {
        $this->assertInstanceOf(ContainerInterface::class, $this->container);
    }

    public function testHasReturnsTrueForBoundAbstract()
    {
        $this->container->bind('thing', function () {
            return new Dep;
        });

        $this->assertTrue($this->container->has('thing'));
    }

    public function testHasReturnsFalseForUnknownAbstract()
    {
        $this->assertFalse($this->container->has('nope'));
    }

    public function testGetOnUnboundUnknownIdThrowsNotFound()
    {
        $this->expectException(NotFoundExceptionInterface::class);

        $this->container->get('Totally\\Unknown\\Id');
    }

    public function testGetOnBoundButThrowingFactoryThrowsContainerExceptionNotNotFound()
    {
        try {
            $this->container->bind('broken', function () {
                return new ThrowingCtor;
            });

            $this->container->get('broken');

            $this->fail('Expected a ContainerExceptionInterface to be thrown.');
        } catch (NotFoundExceptionInterface $e) {
            $this->fail('A bound-but-throwing factory must not surface as NotFoundExceptionInterface: ' . get_class($e));
        } catch (ContainerExceptionInterface $e) {
            $this->assertNotInstanceOf(NotFoundExceptionInterface::class, $e);
        } catch (\Exception $e) {
            $this->fail('Expected a ContainerExceptionInterface, got ' . get_class($e) . ': ' . $e->getMessage());
        }
    }
}
