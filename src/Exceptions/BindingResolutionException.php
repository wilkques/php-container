<?php

namespace Wilkques\Container\Exceptions;

/**
 * Thrown when the container is unable to resolve/build a binding,
 * e.g. an abstract cannot be instantiated or a required dependency
 * cannot be constructed.
 */
class BindingResolutionException extends ContainerException
{
}
