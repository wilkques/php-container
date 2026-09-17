<?php

namespace Wilkques\Container\Tests\Fixtures;

class Collector
{
    /** @var \Wilkques\Container\Tests\Fixtures\Foo[] */
    public $foos;

    public function __construct(Foo ...$foos)
    {
        $this->foos = $foos;
    }
}
