<?php

namespace Wilkques\Container\Tests\Fixtures;

class Child extends Base
{
    /** @var \Wilkques\Container\Tests\Fixtures\Base */
    public $p;

    /**
     * @param parent $p
     */
    public function __construct(parent $p)
    {
        $this->p = $p;
    }
}
