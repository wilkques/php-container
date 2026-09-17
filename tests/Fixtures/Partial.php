<?php

namespace Wilkques\Container\Tests\Fixtures;

class Partial
{
    /** @var \Wilkques\Container\Tests\Fixtures\Dep */
    public $a;

    /** @var mixed */
    public $b;

    /**
     * @param \Wilkques\Container\Tests\Fixtures\Dep $a
     * @param mixed $b
     */
    public function __construct(Dep $a, $b)
    {
        $this->a = $a;
        $this->b = $b;
    }
}
