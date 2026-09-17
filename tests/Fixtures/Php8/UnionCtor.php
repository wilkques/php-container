<?php

namespace Wilkques\Container\Tests\Fixtures\Php8;

use Wilkques\Container\Tests\Fixtures\Dep;

/**
 * PHP 8+ only fixture (nullable + union parameter types). Only ever
 * `require`d behind a PHP_VERSION_ID >= 80000 guard from bootstrap.php.
 */
class UnionCtor
{
    /** @var \Wilkques\Container\Tests\Fixtures\Dep|null */
    public $d;

    /** @var int|string|null */
    public $u;

    public function __construct(?Dep $d, int|string|null $u = 138)
    {
        $this->d = $d;
        $this->u = $u;
    }
}
