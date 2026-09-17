<?php

namespace Wilkques\Container\Tests\Fixtures;

class SelfHint
{
    /** @var \Wilkques\Container\Tests\Fixtures\SelfHint|null */
    public $x;

    /**
     * @param self|null $x
     */
    public function __construct(self $x = null)
    {
        $this->x = $x;
    }
}
