<?php

namespace Wilkques\Container\Tests\Fixtures;

class Invokable
{
    /** @var int */
    public static $calls = 0;

    /**
     * @return int
     */
    public function __invoke()
    {
        static::$calls++;

        return static::$calls;
    }
}
