<?php

namespace Wilkques\Container\Exceptions;

/**
 * Base exception for all errors thrown by the container.
 *
 * Thrown (or extended) whenever the container encounters a generic
 * error while building, binding, or resolving entries.
 */
class ContainerException extends \Exception implements \Psr\Container\ContainerExceptionInterface
{
}
