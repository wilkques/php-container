<?php

namespace Wilkques\Container\Tests\Fixtures;

class ThrowingCtor
{
    public function __construct()
    {
        // \TypeError (PHP 7+) would make this fixture unconstructible on
        // PHP 5.x (the class doesn't exist there at all, and unlike an
        // unresolved catch-clause type, `new \TypeError(...)` fatals the
        // moment it's actually reached). A plain \RuntimeException throws
        // on every PHP version this suite runs under and is just as
        // "always throws" as far as either test using this fixture cares.
        throw new \RuntimeException('ThrowingCtor always throws');
    }
}
