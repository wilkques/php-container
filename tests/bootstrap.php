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
