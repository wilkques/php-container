<?php

namespace Wilkques\Container\Tests\Fixtures\Php56;

/**
 * `...$things` (variadic params) is PHP 5.6+ syntax, so it can't live
 * inline inside VariadicTest.php — the whole file has to parse on every
 * PHP version the suite runs under. Only pulled in from
 * tests/bootstrap.php when PHP_VERSION_ID >= 50600.
 */
class UntypedVariadicCallable
{
    public static function get()
    {
        return function (...$things) {
            return $things;
        };
    }
}
