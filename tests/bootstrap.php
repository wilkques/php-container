<?php

/**
 * Test bootstrap for wilkques/container.
 *
 * Run `composer install` at the package root first; this loads the
 * package's own vendor/autoload.php (PSR-4 for Wilkques\Container\, plus
 * the psr/container dependency declared in composer.json).
 */

require __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(function ($class) {
    $prefix = 'Wilkques\\Container\\Tests\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));

    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// PHP-8-only fixtures use union types / other 8.0+ only syntax, so they live
// in their own files that we only pull in when running on PHP >= 8, to keep
// the rest of the suite parseable on older PHP.
if (PHP_VERSION_ID >= 80000) {
    require __DIR__ . '/Fixtures/Php8/UnionCtor.php';
    require __DIR__ . '/Fixtures/Php8/FooBarUnion.php';
}

// Same idea, one version floor down: nullable scalar/class type hints
// (`?int`, `?ContractInterface`) are PHP 7.1+ only.
if (PHP_VERSION_ID >= 70100) {
    require __DIR__ . '/Fixtures/Php71/NullableScalarCallable.php';
    require __DIR__ . '/Fixtures/Php71/NullableInterfaceCollaborator.php';
}

// Same idea again: variadic params (`...$things`) are PHP 5.6+ only, and
// composer.json's declared floor is 5.3.
if (PHP_VERSION_ID >= 50600) {
    require __DIR__ . '/Fixtures/Php56/UntypedVariadicCallable.php';
}

// composer.json's require-dev pins "phpunit/phpunit": "*", so composer
// resolves whichever major version the running PHP can actually install:
// PHPUnit 4.8 on PHP 5.3, up through PHPUnit 9+ on PHP 7.3+. Two things
// differ across that range and both are compile-time syntax, not something
// a runtime check alone can paper over:
//
//   1. PHPUnit < 6 exposes the global \PHPUnit_Framework_TestCase; PHPUnit
//      >= 6 exposes the PSR-4 \PHPUnit\Framework\TestCase instead. Alias
//      the old name onto the new one so every test file can consistently
//      write `use PHPUnit\Framework\TestCase`.
//   2. PHPUnit versions whose TestCase::setUp()/tearDown() declare a
//      `: void` return type require every override to repeat it (PHP
//      enforces return-type covariance); versions whose setUp() declares
//      no return type at all will fatal ("must be compatible with") if an
//      override adds one PHP itself doesn't support (< 7.1) anyway. So
//      TestCase.php — the only file that overrides setUp()/tearDown() — is
//      a thin dispatcher that requires whichever variant matches what's
//      actually installed, decided here once via reflection on the real,
//      installed TestCase rather than guessing from a PHP/PHPUnit version
//      number. (Subclasses needing their own per-test setup, e.g.
//      CallTest, override TestCase's untyped additionalSetUp() hook
//      instead of setUp() itself, so they never need this treatment.)
if (!class_exists('PHPUnit\\Framework\\TestCase') && class_exists('PHPUnit_Framework_TestCase')) {
    class_alias('PHPUnit_Framework_TestCase', 'PHPUnit\\Framework\\TestCase');
}

if (!defined('WILKQUES_CONTAINER_TESTS_SETUP_NEEDS_VOID')) {
    $needsVoid = false;

    // "(new Foo())->bar()" needs PHP 5.4; composer.json's floor is 5.3.
    if (method_exists('ReflectionMethod', 'hasReturnType')) {
        $setUpReflection = new ReflectionMethod('PHPUnit\\Framework\\TestCase', 'setUp');
        $needsVoid = $setUpReflection->hasReturnType();
    }

    define('WILKQUES_CONTAINER_TESTS_SETUP_NEEDS_VOID', $needsVoid);
}
