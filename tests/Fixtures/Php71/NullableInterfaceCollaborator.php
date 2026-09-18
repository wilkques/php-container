<?php

namespace Wilkques\Container\Tests\Fixtures\Php71;

use Wilkques\Container\Tests\Fixtures\ContractInterface;

/**
 * `?ContractInterface` (nullable class type hint) is PHP 7.1+ syntax, so it
 * can't live inline inside BindingTest.php — the whole file has to parse on
 * every PHP version the suite runs under. Only pulled in from
 * tests/bootstrap.php when PHP_VERSION_ID >= 70100.
 *
 * (The original form of this test built this as an anonymous class, which
 * is itself PHP 7.0+-only syntax — a second, separate reason it couldn't
 * stay inline. A named fixture class fixes both at once.)
 */
class NullableInterfaceCollaborator
{
    public $dep;

    public function __construct(?ContractInterface $dep = null)
    {
        $this->dep = $dep;
    }
}
