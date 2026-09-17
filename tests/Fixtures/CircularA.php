<?php

namespace Wilkques\Container\Tests\Fixtures;

class CircularA
{
    /** @var \Wilkques\Container\Tests\Fixtures\CircularB */
    public $b;

    public function __construct(CircularB $b)
    {
        $this->b = $b;
    }
}
