<?php

namespace Wilkques\Container\Tests\Fixtures;

class ThrowingCtor
{
    public function __construct()
    {
        throw new \TypeError('ThrowingCtor always throws');
    }
}
