<?php

namespace Wilkques\Container\Tests\Fixtures\Php8;

use Wilkques\Container\Tests\Fixtures\Dep;
use Wilkques\Container\Tests\Fixtures\Foo;

/**
 * PHP 8+ only fixture (union parameter type with no null member and no
 * default value). Only ever `require`d behind a PHP_VERSION_ID >= 80000
 * guard from bootstrap.php.
 */
class FooBarUnion
{
    /** @var \Wilkques\Container\Tests\Fixtures\Foo|\Wilkques\Container\Tests\Fixtures\Dep */
    public $x;

    public function __construct(Foo|Dep $x)
    {
        $this->x = $x;
    }
}
