<?php

namespace Wilkques\Container;

class ContextualBindingBuilder
{
    /** @var \Wilkques\Container\Container */
    protected $container;

    /** @var string|array */
    protected $concrete;

    /** @var string */
    protected $needs;

    /**
     * @param \Wilkques\Container\Container $container
     * @param string|array $concrete
     */
    public function __construct($container, $concrete)
    {
        $this->container = $container;
        $this->concrete = $concrete;
    }

    /**
     * Define the abstract target that depends on the context.
     *
     * @param string $abstract
     *
     * @return static
     */
    public function needs($abstract)
    {
        $this->needs = $abstract;

        return $this;
    }

    /**
     * Define the implementation for the contextual binding.
     *
     * @param \Closure|string|mixed $implementation
     *
     * @return void
     */
    public function give($implementation)
    {
        foreach ((array) $this->concrete as $concrete) {
            $this->container->addContextualBinding($concrete, $this->needs, $implementation);
        }
    }
}
