<?php

namespace Wilkques\Container\Tests\Fixtures;

class NestedImpl implements ContractInterface
{
    /** @var \Wilkques\Container\Tests\Fixtures\Dep */
    public $dep;

    public function __construct(Dep $dep)
    {
        $this->dep = $dep;
    }
}
