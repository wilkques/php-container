<?php

namespace Wilkques\Container\Tests;

use Wilkques\Container\Exceptions\BindingResolutionException;
use Wilkques\Container\Exceptions\CircularDependencyException;
use Wilkques\Container\Tests\Fixtures\Base;
use Wilkques\Container\Tests\Fixtures\ChainA;
use Wilkques\Container\Tests\Fixtures\ChainB;
use Wilkques\Container\Tests\Fixtures\Child;
use Wilkques\Container\Tests\Fixtures\CircularA;
use Wilkques\Container\Tests\Fixtures\CircularB;
use Wilkques\Container\Tests\Fixtures\ContractInterface;
use Wilkques\Container\Tests\Fixtures\Dep;
use Wilkques\Container\Tests\Fixtures\SelfCircular;
use Wilkques\Container\Tests\Fixtures\SelfHint;
use Wilkques\Container\Tests\Fixtures\SomeAbstract;
use Wilkques\Container\Tests\Fixtures\UntypedNoDefault;

/**
 * B7, B8, B9, N10, and optional-param semantics.
 */
class AutowiringTest extends TestCase
{
    /**
     * B7: mutual circular dependency must be detected, not exhaust memory.
     *
     * Run in a separate process: today's src/ has no cycle detection at
     * all, so an unguarded run recurses until PHP's "Allowed memory size
     * exhausted" fatal error fires — and that kind of OOM is NOT a
     * catchable \Throwable, it kills the whole PHP process outright. If
     * this test ran in-process it would take the entire suite down with
     * it. Isolating it lets a real regression fail fast (this single test
     * errors) instead of aborting every other test in the run.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testMutualCircularDependencyThrows()
    {
        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', '32M');

        // No `finally` (PHP 5.5+ only; composer.json's floor is 5.3) — the
        // catch-all below stands in for it, restoring memory_limit on any
        // throw, expected or not, before the final line covers the
        // (unreachable in practice, since expectException() demands one)
        // no-throw path.
        try {
            $this->expectException(CircularDependencyException::class);

            try {
                $this->container->make(CircularA::class);
            } catch (CircularDependencyException $e) {
                $this->assertStringContainsStringCompat(CircularA::class, $e->getMessage());
                $this->assertStringContainsStringCompat(CircularB::class, $e->getMessage());

                throw $e;
            }
        } catch (\Exception $e) {
            ini_set('memory_limit', $previousLimit);

            throw $e;
        }

        ini_set('memory_limit', $previousLimit);
    }

    /**
     * B7: a class depending on itself must also be detected as circular.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSelfCircularDependencyThrows()
    {
        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', '32M');

        // See testMutualCircularDependencyThrows() re: no `finally`.
        try {
            $this->expectException(CircularDependencyException::class);

            $this->container->make(SelfCircular::class);
        } catch (\Exception $e) {
            ini_set('memory_limit', $previousLimit);

            throw $e;
        }

        ini_set('memory_limit', $previousLimit);
    }

    /** B8: `parent` type-hint resolves to an instance of the parent class. */
    public function testParentTypeHintInjectsParentClassInstance()
    {
        $child = $this->container->make(Child::class);

        $this->assertInstanceOf(Base::class, $child->p);
    }

    /** B8: `self` type-hint is resolvable (with a default of null, per the fixture). */
    public function testSelfTypeHintIsResolvable()
    {
        if (PHP_VERSION_ID < 70100) {
            $this->markTestSkipped('Fixtures\\SelfHint uses a nullable `?self` param, which requires PHP 7.1');
        }

        $result = $this->container->make(SelfHint::class);

        $this->assertInstanceOf(SelfHint::class, $result);
    }

    /** B9: an unbound interface cannot be instantiated. */
    public function testUnboundInterfaceThrowsNotInstantiable()
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessageMatchesCompat('/not instantiable/i');

        $this->container->make(ContractInterface::class);
    }

    /** B9: an unbound abstract class cannot be instantiated. */
    public function testUnboundAbstractClassThrowsNotInstantiable()
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessageMatchesCompat('/not instantiable/i');

        $this->container->make(SomeAbstract::class);
    }

    /** B9: a class that doesn't exist at all gives a distinct error message. */
    public function testMissingClassThrowsDoesNotExist()
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessageMatchesCompat('/does not exist/i');

        $this->container->make('Totally\\Missing\\ClassName');
    }

    /** B9: a nested resolution failure reports the build chain, e.g. "[ChainA] -> [ChainB]". */
    public function testNestedFailureReportsBuildChain()
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessageMatchesCompat('/\[.*ChainA.*\].*->.*\[.*ChainB.*\]/s');

        $this->container->make(ChainA::class);
    }

    /** N10 (PHP 8 only): nullable + union ctor params resolve per their real semantics. */
    public function testUnionCtorResolvesRealDependencyAndDefaultScalar()
    {
        if (PHP_VERSION_ID < 80000) {
            $this->markTestSkipped('Union types require PHP 8');
        }

        $result = $this->container->make(\Wilkques\Container\Tests\Fixtures\Php8\UnionCtor::class);

        $this->assertInstanceOf(Dep::class, $result->d);
        $this->assertSame(138, $result->u);
    }

    /** N10 (PHP 8 only): a non-nullable, no-default union resolves to one of its members. */
    public function testNonNullableUnionResolvesToOneOfItsMembers()
    {
        if (PHP_VERSION_ID < 80000) {
            $this->markTestSkipped('Union types require PHP 8');
        }

        $result = $this->container->make(\Wilkques\Container\Tests\Fixtures\Php8\FooBarUnion::class);

        $this->assertThat(
            $result->x,
            $this->logicalOr(
                $this->isInstanceOf(\Wilkques\Container\Tests\Fixtures\Foo::class),
                $this->isInstanceOf(Dep::class)
            )
        );
    }

    /** Optional-param semantics: `?int $x` with no default resolves to null. */
    public function testNullableScalarWithNoDefaultResolvesToNull()
    {
        if (PHP_VERSION_ID < 70100) {
            $this->markTestSkipped('Nullable scalar type hints require PHP 7.1');
        }

        $result = $this->container->call(\Wilkques\Container\Tests\Fixtures\Php71\NullableScalarCallable::get());

        $this->assertNull($result);
    }

    /**
     * Optional-param semantics: an *untyped* `$x` with no default must NOT
     * silently become null (ReflectionParameter::allowsNull() is true for
     * untyped params on every PHP version — that must not be mistaken for
     * "has a usable default").
     */
    public function testUntypedParamWithNoDefaultThrows()
    {
        $this->expectException(BindingResolutionException::class);

        $this->container->make(UntypedNoDefault::class);
    }
}
