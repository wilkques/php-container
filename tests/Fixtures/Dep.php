<?php

namespace Wilkques\Container\Tests\Fixtures;

class Dep
{
    /**
     * Used by LegacyReadmeApiTest to exercise the documented `call()` forms.
     *
     * @param string $label
     *
     * @return string
     */
    public function describe($label)
    {
        return $label;
    }
}
