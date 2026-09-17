<?php

namespace Wilkques\Container\Exceptions;

/**
 * Thrown when the container detects a circular dependency while
 * building an entry, i.e. resolving an abstract requires resolving
 * itself again somewhere down the build stack.
 */
class CircularDependencyException extends BindingResolutionException
{
}
