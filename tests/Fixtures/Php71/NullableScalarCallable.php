<?php

namespace Wilkques\Container\Tests\Fixtures\Php71;

/**
 * `?int` (nullable scalar type hint) is PHP 7.1+ syntax, so it can't live
 * inline inside AutowiringTest.php — the whole file has to parse on every
 * PHP version the suite runs under, even in a test method that skips
 * itself at runtime. Only pulled in from tests/bootstrap.php when
 * PHP_VERSION_ID >= 70100.
 */
class NullableScalarCallable
{
    public static function get()
    {
        return function (?int $x) {
            return $x;
        };
    }
}
