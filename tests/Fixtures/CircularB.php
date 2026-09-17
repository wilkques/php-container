<?php

namespace Wilkques\Container\Tests\Fixtures;

class CircularB
{
    /** @var \Wilkques\Container\Tests\Fixtures\CircularA */
    public $a;

    public function __construct(CircularA $a)
    {
        $this->a = $a;
    }
}
