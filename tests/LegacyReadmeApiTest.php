<?php

namespace Wilkques\Container\Tests;

use Wilkques\Container\Tests\Fixtures\Dep;

/**
 * Transcribes every fenced code block in README.md into an executable test.
 * This is the documented BC contract for the library — these calls must
 * keep working exactly as documented across the refactor.
 */
class LegacyReadmeApiTest extends TestCase
{
    /**
     * ```php
     * container()->register(
     * '<your class name>',
     * new '<your class name>'
     * );
     *
     * // or
     *
     * container()->register([
     *     [
     *         '<your class name1>',
     *         new '<your class name1>'
     *     ],
     *     [
     *         '<your class name2>',
     *         new '<your class name2>'
     *     ],
     *
     *     ...
     * ]);
     * ```
     */
    public function testRegisterSingle()
    {
        $instance = new Dep;

        $this->container->register(Dep::class, $instance);

        $this->assertSame($instance, $this->container->get(Dep::class));
    }

    public function testRegisterBatch()
    {
        $one = new Dep;
        $two = new Dep;

        $this->container->register(array(
            array('class1', $one),
            array('class2', $two),
        ));

        $this->assertSame($one, $this->container->get('class1'));
        $this->assertSame($two, $this->container->get('class2'));
    }

    /**
     * ```php
     * $abstract = new \Your\Class\Name;
     *
     * container()->bind('<your class name>', function () use ($abstract) {
     *     return $abstract;
     * });
     * ```
     */
    public function testBind()
    {
        $abstract = new Dep;

        $this->container->bind('the-class', function () use ($abstract) {
            return $abstract;
        });

        $this->assertSame($abstract, $this->container->make('the-class'));
    }

    /**
     * ```php
     * $abstract = new \Your\Class\Name;
     *
     * container()->singleton('<your class name>', function () use ($abstract) {
     *     return $abstract;
     * });
     * ```
     */
    public function testSingleton()
    {
        $abstract = new Dep;

        $this->container->singleton('the-class', function () use ($abstract) {
            return $abstract;
        });

        $this->assertSame($abstract, $this->container->make('the-class'));
        $this->assertSame($abstract, $this->container->make('the-class'));
    }

    /**
     * ```php
     * $abstract = new \Your\Class\Name;
     *
     * container()->scoped('<your class name>', function () use ($abstract) {
     *     return $abstract;
     * });
     * ```
     */
    public function testScoped()
    {
        $abstract = new Dep;

        $this->container->scoped('the-class', function () use ($abstract) {
            return $abstract;
        });

        $this->assertSame($abstract, $this->container->make('the-class'));
    }

    /**
     * ```php
     * container()->get('<your class name>');
     * ```
     */
    public function testGet()
    {
        $abstract = new Dep;

        $this->container->register('the-class', $abstract);

        $this->assertSame($abstract, $this->container->get('the-class'));
    }

    /**
     * ```php
     * container('<your class name>');
     *
     * // or
     *
     * container()->make('<your class name>');
     * ```
     */
    public function testMake()
    {
        $abstract = new Dep;

        $this->container->register('the-class', $abstract);

        $this->assertSame($abstract, $this->container->make('the-class'));
    }

    /**
     * ```php
     * container()->call(['<your class name>', '<your class method name>'], ['<your class method vars name>' => '<your class method vars value>']);
     *
     * // or
     *
     * container()->call([new '<your class name>', '<your class method name>'], ['<your class method vars name>' => '<your class method vars value>']);
     *
     * // or
     *
     * container()->call(function (\Your\Class\Name $abstract) {
     *     // do something
     * });
     * ```
     */
    public function testCallWithClassNameAndMethodName()
    {
        $result = $this->container->call(
            array(Dep::class, 'describe'),
            array('label' => 'hi')
        );

        $this->assertSame('hi', $result);
    }

    public function testCallWithInstanceAndMethodName()
    {
        $instance = new Dep;

        $result = $this->container->call(
            array($instance, 'describe'),
            array('label' => 'hello')
        );

        $this->assertSame('hello', $result);
    }

    public function testCallWithClosure()
    {
        $result = $this->container->call(function (Dep $abstract) {
            return $abstract;
        });

        $this->assertInstanceOf(Dep::class, $result);
    }

    /**
     * ```
     * `forgetScopedInstances`
     * Clear all of the scoped instances from the container.
     * ```
     */
    public function testForgetScopedInstances()
    {
        $abstract = new Dep;

        $this->container->scoped('the-class', function () use ($abstract) {
            return $abstract;
        });

        $this->container->make('the-class');

        $this->container->forgetScopedInstances();

        $instances = $this->peek($this->container, 'instances');

        $this->assertArrayNotHasKey('the-class', $instances);
    }

    /**
     * ```php
     * container()->forgetInstance('<your class name>');
     * ```
     */
    public function testForgetInstance()
    {
        $abstract = new Dep;

        $this->container->register('the-class', $abstract);

        $this->container->forgetInstance('the-class');

        $instances = $this->peek($this->container, 'instances');

        $this->assertArrayNotHasKey('the-class', $instances);
    }

    /**
     * ```
     * `forgetInstances`
     * Clear all of the instances from the container.
     * ```
     */
    public function testForgetInstances()
    {
        $this->container->register('a', new Dep);
        $this->container->register('b', new Dep);

        $this->container->forgetInstances();

        $instances = $this->peek($this->container, 'instances');

        $this->assertArrayNotHasKey('a', $instances);
        $this->assertArrayNotHasKey('b', $instances);
    }

    /**
     * ```
     * `flush`
     * Flush the container of all bindings and resolved instances.
     * ```
     */
    public function testFlush()
    {
        $this->container->register('a', new Dep);

        $this->container->flush();

        $instances = $this->peek($this->container, 'instances');

        $this->assertArrayNotHasKey('a', $instances);
    }
}
