<?php

namespace Wilkques\Container\Tests\Fixtures;

class UntypedNoDefault
{
    public $x;

    public function __construct($x)
    {
        $this->x = $x;
    }
}
