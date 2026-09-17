<?php

namespace Wilkques\Container\Tests\Fixtures;

class Counter
{
    /** @var int */
    public static $count = 0;

    public function __construct()
    {
        static::$count++;
    }

    /**
     * @return string
     */
    public function whoAmI()
    {
        return spl_object_hash($this);
    }

    /**
     * @return string
     */
    public static function staticWhoAmI()
    {
        return 'static-result';
    }
}
