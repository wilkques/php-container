<?php

namespace Wilkques\Container\Exceptions;

/**
 * Thrown when no entry was found in the container for a given
 * identifier (PSR-11 "not found" case).
 */
class NotFoundException extends ContainerException implements \Psr\Container\NotFoundExceptionInterface
{
}
