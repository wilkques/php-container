<?php

namespace Wilkques\Container\Tests\Fixtures;

class SelfCircular
{
    /** @var \Wilkques\Container\Tests\Fixtures\SelfCircular */
    public $x;

    public function __construct(SelfCircular $x)
    {
        $this->x = $x;
    }
}
