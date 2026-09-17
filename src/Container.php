<?php

namespace Wilkques\Container;

/**
 * @see [wilkques](https://github.com/wilkques/container)
 *
 * create by: wilkques
 *
 * PHP 5.3 compatible DI container, aligned with Illuminate\Container\Container
 * semantics where practical.
 */
class Container implements \Psr\Container\ContainerInterface
{
    /**
     * @var static
     */
    protected static $instance;

    /**
     * The container's lazy binding definitions.
     *
     * abstract => array('concrete' => mixed, 'shared' => bool)
     *
     * @var array
     */
    protected $bindings = array();

    /**
     * The container's resolved shared instances.
     *
     * @var array
     */
    protected $instances = array();

    /**
     * @var array
     */
    protected $aliases = array();

    /**
     * @var array
     */
    protected $abstractAliases = array();

    /**
     * @var array
     */
    protected $tags = array();

    /**
     * @var array
     */
    protected $extenders = array();

    /**
     * Contextual bindings.
     *
     * concreteClass => array(needle => implementation)
     *
     * needle is either a class/type name, or '$paramName'.
     *
     * @var array
     */
    protected $contextual = array();

    /**
     * @var array
     */
    protected $buildStack = array();

    /**
     * Stack of parameter-override frames, one per in-flight resolve() call.
     *
     * @var array
     */
    protected $with = array();

    /**
     * @var array
     */
    protected $scopedInstances = array();

    /**
     * @var array
     */
    protected $resolved = array();

    /**
     * @var array
     */
    protected $globalResolvingCallbacks = array();

    /**
     * @var array
     */
    protected $resolvingCallbacks = array();

    /**
     * @var array
     */
    protected $globalAfterResolvingCallbacks = array();

    /**
     * @var array
     */
    protected $afterResolvingCallbacks = array();

    public function __construct()
    {
        $this->instance(__CLASS__, $this);

        $this->instance(get_class($this), $this);
    }

    /*
    |--------------------------------------------------------------------------
    | Legacy / BC API
    |--------------------------------------------------------------------------
    */

    /**
     * Register a abstract with the container.
     *
     * @param string|array $abstract
     * @param mixed $concrete
     *
     * @return static
     */
    public function register($abstract, $concrete = null)
    {
        if (is_array($abstract)) {
            foreach ($abstract as $pair) {
                if (is_array($pair) && array_key_exists(0, $pair)) {
                    $pairAbstract = $pair[0];
                    $pairConcrete = array_key_exists(1, $pair) ? $pair[1] : null;

                    $this->register($pairAbstract, $pairConcrete);
                } else {
                    $this->register($pair);
                }
            }

            return $this;
        }

        $this->instance($abstract, $concrete);

        return $this;
    }

    /**
     * @deprecated Use instance() instead.
     *
     * @param string $abstract
     * @param mixed $object
     *
     * @return static
     */
    public function bindAbstract($abstract, $object)
    {
        $this->instance($abstract, $object);

        return $this;
    }

    /**
     * @deprecated Use make() (with an $abstract) or read the container's
     *             resolved instances directly (no $abstract) instead.
     *
     * @param string|null $abstract
     *
     * @return mixed
     */
    public function resolveAbstract($abstract = null)
    {
        if (is_null($abstract)) {
            return $this->instances;
        }

        return $this->make($abstract);
    }

    /*
    |--------------------------------------------------------------------------
    | Binding
    |--------------------------------------------------------------------------
    */

    /**
     * Bind a new type into the container.
     *
     * @param string $abstract
     * @param \Closure|string|mixed|null $concrete
     * @param bool $shared
     *
     * @return static
     */
    public function bind($abstract, $concrete = null, $shared = false)
    {
        $this->dropStaleInstances($abstract);

        if (is_null($concrete)) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = array(
            'concrete' => $concrete,
            'shared' => $shared,
        );

        return $this;
    }

    /**
     * Register a shared binding in the container.
     *
     * @param string $abstract
     * @param \Closure|string|mixed|null $concrete
     *
     * @return static
     */
    public function singleton($abstract, $concrete = null)
    {
        return $this->bind($abstract, $concrete, true);
    }

    /**
     * Register a scoped binding in the container.
     *
     * @param string $abstract
     * @param \Closure|string|mixed|null $concrete
     *
     * @return static
     */
    public function scoped($abstract, $concrete = null)
    {
        if (!in_array($abstract, $this->scopedInstances, true)) {
            $this->scopedInstances[] = $abstract;
        }

        return $this->singleton($abstract, $concrete);
    }

    /**
     * Register an existing, already-resolved instance into the container.
     *
     * @param string $abstract
     * @param mixed $instance
     *
     * @return mixed
     */
    public function instance($abstract, $instance)
    {
        $this->removeAbstractAlias($abstract);

        unset($this->aliases[$abstract]);

        $this->instances[$abstract] = $instance;

        return $instance;
    }

    /**
     * Drop stale instances/aliases for the given abstract.
     *
     * @param string $abstract
     *
     * @return void
     */
    protected function dropStaleInstances($abstract)
    {
        unset($this->instances[$abstract], $this->aliases[$abstract]);
    }

    /**
     * Remove an alias from the abstractAliases reverse-lookup map.
     *
     * @param string $searched
     *
     * @return void
     */
    protected function removeAbstractAlias($searched)
    {
        if (!isset($this->aliases[$searched])) {
            return;
        }

        foreach ($this->abstractAliases as $abstract => $aliases) {
            foreach ($aliases as $index => $alias) {
                if ($alias === $searched) {
                    unset($this->abstractAliases[$abstract][$index]);
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Aliases
    |--------------------------------------------------------------------------
    */

    /**
     * Alias a type to a different name.
     *
     * @param string $abstract
     * @param string $alias
     *
     * @return static
     */
    public function alias($abstract, $alias)
    {
        if ($alias === $abstract) {
            throw new \LogicException("[{$abstract}] is aliased to itself.");
        }

        $this->aliases[$alias] = $abstract;

        $this->abstractAliases[$abstract][] = $alias;

        return $this;
    }

    /**
     * Get the alias for an abstract if available.
     *
     * @param string $abstract
     *
     * @return string
     */
    public function getAlias($abstract)
    {
        if (!isset($this->aliases[$abstract])) {
            return $abstract;
        }

        return $this->getAlias($this->aliases[$abstract]);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function isAlias($name)
    {
        return isset($this->aliases[$name]);
    }

    /*
    |--------------------------------------------------------------------------
    | Contextual bindings
    |--------------------------------------------------------------------------
    */

    /**
     * Define a contextual binding.
     *
     * @param string|array $concrete
     *
     * @return \Wilkques\Container\ContextualBindingBuilder
     */
    public function when($concrete)
    {
        return new ContextualBindingBuilder($this, $concrete);
    }

    /**
     * Add a contextual binding to the container.
     *
     * @param string $concrete
     * @param string $abstract
     * @param \Closure|string|mixed $implementation
     *
     * @return static
     */
    public function addContextualBinding($concrete, $abstract, $implementation)
    {
        $this->contextual[$concrete][$abstract] = $implementation;

        return $this;
    }

    /**
     * Get the contextual concrete binding for the given needle (a class/type
     * name, or '$paramName'), scoped to the class currently being built.
     *
     * @param string $abstract
     * @param bool $found set by reference: whether a binding was actually found
     *
     * @return mixed
     */
    protected function getContextualConcrete($abstract, &$found = null)
    {
        $found = false;

        $building = end($this->buildStack);

        if ($building === false) {
            return null;
        }

        if (isset($this->contextual[$building]) && array_key_exists($abstract, $this->contextual[$building])) {
            $found = true;

            return $this->contextual[$building][$abstract];
        }

        return null;
    }

    /**
     * Resolve a contextual "give()" value into its final form.
     *
     * @param mixed $concrete
     *
     * @return mixed
     */
    protected function resolveContextualValue($concrete)
    {
        if ($concrete instanceof \Closure) {
            return call_user_func($concrete, $this);
        }

        if (is_string($concrete) && ($this->bound($concrete) || class_exists($concrete))) {
            return $this->make($concrete);
        }

        return $concrete;
    }

    /*
    |--------------------------------------------------------------------------
    | Resolving
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the given abstract from the container.
     *
     * @param string $abstract
     * @param array $parameters
     *
     * @return mixed
     */
    public function make($abstract, $parameters = array())
    {
        return $this->resolve($abstract, $parameters);
    }

    /**
     * @param string $abstract
     * @param array $parameters
     * @param bool $raiseEvents
     *
     * @return mixed
     */
    protected function resolve($abstract, $parameters = array(), $raiseEvents = true)
    {
        $abstract = $this->getAlias($abstract);

        $found = false;
        $contextualConcrete = $this->getContextualConcrete($abstract, $found);

        $needsContextualBuild = !empty($parameters) || $found;

        if (isset($this->instances[$abstract]) && !$needsContextualBuild) {
            return $this->instances[$abstract];
        }

        $this->with[] = $parameters;

        try {
            if ($found) {
                $object = $this->resolveContextualValue($contextualConcrete);
            } else {
                $concrete = $this->getConcrete($abstract);

                if ($this->isBuildable($concrete, $abstract)) {
                    $object = $this->build($concrete);
                } else {
                    $object = $this->resolve($concrete, array(), false);
                }
            }
        } catch (\Exception $e) {
            array_pop($this->with);

            throw $e;
        } catch (\Throwable $e) {
            array_pop($this->with);

            throw $e;
        }

        foreach ($this->getExtenders($abstract) as $extender) {
            $object = call_user_func($extender, $object, $this);
        }

        if ($this->isShared($abstract) && !$needsContextualBuild) {
            $this->instances[$abstract] = $object;
        }

        if ($raiseEvents) {
            $this->fireResolvingCallbacks($abstract, $object);
        }

        $this->resolved[$abstract] = true;

        array_pop($this->with);

        return $object;
    }

    /**
     * @param string $abstract
     *
     * @return mixed
     */
    protected function getConcrete($abstract)
    {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    /**
     * @param mixed $concrete
     * @param string $abstract
     *
     * @return bool
     */
    protected function isBuildable($concrete, $abstract)
    {
        return $concrete === $abstract || $concrete instanceof \Closure;
    }

    /**
     * @param string $abstract
     *
     * @return bool
     */
    protected function isShared($abstract)
    {
        return isset($this->bindings[$abstract]['shared']) && $this->bindings[$abstract]['shared'] === true;
    }

    /**
     * @param string $abstract
     *
     * @return array
     */
    protected function getExtenders($abstract)
    {
        return isset($this->extenders[$abstract]) ? $this->extenders[$abstract] : array();
    }

    /**
     * Extend an abstract type in the container.
     *
     * @param string $abstract
     * @param \Closure $closure
     *
     * @return static
     */
    public function extend($abstract, \Closure $closure)
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->instances[$abstract])) {
            $this->instances[$abstract] = call_user_func($closure, $this->instances[$abstract], $this);
        } else {
            $this->extenders[$abstract][] = $closure;
        }

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Building
    |--------------------------------------------------------------------------
    */

    /**
     * @param \Closure|string $concrete
     *
     * @return mixed
     */
    protected function build($concrete)
    {
        if ($concrete instanceof \Closure) {
            return $this->buildClosure($concrete);
        }

        if (in_array($concrete, $this->buildStack, true)) {
            $chain = $this->buildStack;
            $chain[] = $concrete;

            throw new Exceptions\CircularDependencyException(
                'Circular dependency detected while building ' . $this->formatBuildChain($chain) . '.'
            );
        }

        try {
            $reflector = new \ReflectionClass($concrete);
        } catch (\ReflectionException $e) {
            throw new Exceptions\BindingResolutionException("Target class [{$concrete}] does not exist.", 0, $e);
        }

        if (!$reflector->isInstantiable()) {
            $this->notInstantiable($concrete);
        }

        $this->buildStack[] = $concrete;

        try {
            $constructor = $reflector->getConstructor();

            if (is_null($constructor)) {
                $object = new $concrete;
            } else {
                $overrides = $this->getLastParameterOverride();

                $dependencies = $this->resolveDependencies($constructor->getParameters(), $overrides);

                $object = $reflector->newInstanceArgs($dependencies);
            }
        } catch (Exceptions\CircularDependencyException $e) {
            array_pop($this->buildStack);

            throw $e;
        } catch (Exceptions\BindingResolutionException $e) {
            array_pop($this->buildStack);

            throw $e;
        } catch (\Exception $e) {
            array_pop($this->buildStack);

            throw new Exceptions\BindingResolutionException($e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            array_pop($this->buildStack);

            throw new Exceptions\BindingResolutionException($e->getMessage(), 0, $e);
        }

        array_pop($this->buildStack);

        return $object;
    }

    /**
     * @param \Closure $concrete
     *
     * @return mixed
     */
    protected function buildClosure($concrete)
    {
        try {
            return call_user_func($concrete, $this, $this->getLastParameterOverride());
        } catch (Exceptions\BindingResolutionException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new Exceptions\BindingResolutionException($e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new Exceptions\BindingResolutionException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @param string $concrete
     *
     * @return void
     */
    protected function notInstantiable($concrete)
    {
        $chain = $this->buildStack;
        $chain[] = $concrete;

        throw new Exceptions\BindingResolutionException(
            'Target is not instantiable while building ' . $this->formatBuildChain($chain) . '.'
        );
    }

    /**
     * @param array $chain
     *
     * @return string
     */
    protected function formatBuildChain($chain)
    {
        $wrapped = array();

        foreach ($chain as $link) {
            $wrapped[] = '[' . $link . ']';
        }

        return implode(' -> ', $wrapped);
    }

    /**
     * @return array
     */
    protected function getLastParameterOverride()
    {
        return count($this->with) ? end($this->with) : array();
    }

    /*
    |--------------------------------------------------------------------------
    | Dependency resolution
    |--------------------------------------------------------------------------
    */

    /**
     * @param \ReflectionParameter[] $dependencies
     * @param array $overrides
     *
     * @return array
     */
    protected function resolveDependencies($dependencies, $overrides)
    {
        $results = array();

        foreach ($dependencies as $dependency) {
            if ($this->hasParameterOverride($dependency, $overrides)) {
                $value = $this->getParameterOverride($dependency, $overrides);

                if ($this->isVariadic($dependency)) {
                    $results = array_merge($results, (array) $value);
                } else {
                    $results[] = $value;
                }

                continue;
            }

            $result = $this->resolveDependency($dependency);

            if ($this->isVariadic($dependency)) {
                $results = array_merge($results, (array) $result);
            } else {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * @param \ReflectionParameter $dependency
     * @param array $overrides
     *
     * @return bool
     */
    protected function hasParameterOverride($dependency, $overrides)
    {
        return array_key_exists($dependency->getName(), $overrides)
            || array_key_exists($dependency->getPosition(), $overrides);
    }

    /**
     * @param \ReflectionParameter $dependency
     * @param array $overrides
     *
     * @return mixed
     */
    protected function getParameterOverride($dependency, $overrides)
    {
        if (array_key_exists($dependency->getName(), $overrides)) {
            return $overrides[$dependency->getName()];
        }

        return $overrides[$dependency->getPosition()];
    }

    /**
     * @param \ReflectionParameter $parameter
     *
     * @return bool
     */
    protected function isVariadic($parameter)
    {
        return method_exists($parameter, 'isVariadic') && $parameter->isVariadic();
    }

    /**
     * @param \ReflectionParameter $parameter
     *
     * @return mixed
     */
    protected function resolveDependency($parameter)
    {
        $found = false;
        $concrete = $this->getContextualConcrete('$' . $parameter->getName(), $found);

        if ($found) {
            if ($this->isVariadic($parameter) && is_array($concrete)) {
                $results = array();

                foreach ($concrete as $item) {
                    $results[] = $this->resolveContextualValue($item);
                }

                return $results;
            }

            return $this->resolveContextualValue($concrete);
        }

        $className = $this->getParameterClassName($parameter);

        if (!is_null($className)) {
            return $this->resolveClassDependency($parameter, $className);
        }

        $type = $this->getParameterReflectionType($parameter);

        if (!is_null($type) && $this->isNonNamedType($type)) {
            return $this->resolveNonNamedTypeDependency($parameter, $type);
        }

        return $this->resolvePrimitive($parameter, $type);
    }

    /**
     * @param \ReflectionParameter $parameter
     * @param string $className
     *
     * @return mixed
     */
    protected function resolveClassDependency($parameter, $className)
    {
        if ($this->isVariadic($parameter)) {
            return $this->resolveVariadicClass($parameter, $className);
        }

        try {
            return $this->make($className);
        } catch (Exceptions\BindingResolutionException $e) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            $type = $this->getParameterReflectionType($parameter);

            if (!is_null($type) && $type->allowsNull()) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * @param \ReflectionParameter $parameter
     * @param string $className
     *
     * @return array
     */
    protected function resolveVariadicClass($parameter, $className)
    {
        $abstract = $this->getAlias($className);

        $found = false;
        $concrete = $this->getContextualConcrete($abstract, $found);

        if ($found && is_array($concrete)) {
            $results = array();

            foreach ($concrete as $item) {
                $results[] = $this->resolveContextualValue($item);
            }

            return $results;
        }

        return array($this->make($className));
    }

    /**
     * @param \ReflectionParameter $parameter
     * @param \ReflectionType $type
     *
     * @return mixed
     */
    protected function resolveNonNamedTypeDependency($parameter, $type)
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($type->allowsNull()) {
            return null;
        }

        if (method_exists($type, 'getTypes')) {
            foreach ($type->getTypes() as $member) {
                if ($member instanceof \ReflectionNamedType && !$member->isBuiltin()) {
                    try {
                        return $this->make($member->getName());
                    } catch (Exceptions\BindingResolutionException $e) {
                        continue;
                    }
                }
            }
        }

        throw new Exceptions\BindingResolutionException(
            'Unable to resolve dependency $' . $parameter->getName() . ' for ' . $this->getDeclaringClassName($parameter) . '.'
        );
    }

    /**
     * @param \ReflectionParameter $parameter
     * @param \ReflectionType|null $type
     *
     * @return mixed
     */
    protected function resolvePrimitive($parameter, $type)
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($this->isVariadic($parameter)) {
            return array();
        }

        // Only trust allowsNull() once we know a real ReflectionType exists:
        // ReflectionParameter::allowsNull() is true for UNTYPED params on
        // every PHP version, which must not be mistaken for "nullable".
        if (!is_null($type) && $type->allowsNull()) {
            return null;
        }

        throw new Exceptions\BindingResolutionException(
            'Unable to resolve dependency $' . $parameter->getName() . ' for ' . $this->getDeclaringClassName($parameter) . '.'
        );
    }

    /**
     * @param \ReflectionParameter $parameter
     *
     * @return \ReflectionType|null
     */
    protected function getParameterReflectionType($parameter)
    {
        if (!class_exists('ReflectionType')) {
            return null;
        }

        return $parameter->getType();
    }

    /**
     * @param \ReflectionType $type
     *
     * @return bool
     */
    protected function isNonNamedType($type)
    {
        return class_exists('ReflectionNamedType') && !($type instanceof \ReflectionNamedType);
    }

    /**
     * Get the class name of the given parameter's type, if it has one.
     *
     * @param \ReflectionParameter $parameter
     *
     * @return string|null
     */
    protected function getParameterClassName($parameter)
    {
        if (!class_exists('ReflectionType')) {
            try {
                $class = $parameter->getClass();
            } catch (\ReflectionException $e) {
                throw new Exceptions\BindingResolutionException($e->getMessage(), 0, $e);
            }

            return $class ? $class->getName() : null;
        }

        $type = $parameter->getType();

        if (!$type) {
            return null;
        }

        if ($this->isNonNamedType($type)) {
            return null;
        }

        $isBuiltin = method_exists($type, 'isBuiltin') ? $type->isBuiltin() : false;

        if ($isBuiltin) {
            return null;
        }

        $name = method_exists($type, 'getName') ? $type->getName() : ltrim((string) $type, '?');

        $declaringClass = method_exists($parameter, 'getDeclaringClass') ? $parameter->getDeclaringClass() : null;

        if ($declaringClass) {
            if ($name === 'self') {
                return $declaringClass->getName();
            }

            if ($name === 'parent') {
                $parentClass = $declaringClass->getParentClass();

                if ($parentClass) {
                    return $parentClass->getName();
                }
            }
        }

        return $name;
    }

    /**
     * @param \ReflectionParameter $parameter
     *
     * @return string
     */
    protected function getDeclaringClassName($parameter)
    {
        $declaringClass = method_exists($parameter, 'getDeclaringClass') ? $parameter->getDeclaringClass() : null;

        if ($declaringClass) {
            return $declaringClass->getName();
        }

        if (method_exists($parameter, 'getDeclaringFunction')) {
            $function = $parameter->getDeclaringFunction();

            return $function ? $function->getName() : '';
        }

        return '';
    }

    /*
    |--------------------------------------------------------------------------
    | Calling
    |--------------------------------------------------------------------------
    */

    /**
     * Call the given callable and inject its dependencies.
     *
     * @param array|string|object|\Closure $callback
     * @param array $parameters
     *
     * @return mixed
     */
    public function call($callback, $parameters = array())
    {
        if (is_string($callback)) {
            if (strpos($callback, '::') !== false) {
                $callback = explode('::', $callback, 2);
            } elseif (strpos($callback, '@') !== false) {
                $callback = explode('@', $callback, 2);
            }
        }

        if (is_array($callback)) {
            $target = $callback[0];
            $method = isset($callback[1]) ? $callback[1] : '__invoke';

            if (is_object($target)) {
                return $this->callInstanceMethod($target, $method, $parameters);
            }

            return $this->callClassMethod($target, $method, $parameters);
        }

        if (is_object($callback) && !($callback instanceof \Closure)) {
            return $this->callInstanceMethod($callback, '__invoke', $parameters);
        }

        return $this->callFunction($callback, $parameters);
    }

    /**
     * @param object $instance
     * @param string $method
     * @param array $parameters
     *
     * @return mixed
     */
    protected function callInstanceMethod($instance, $method, $parameters)
    {
        $reflector = new \ReflectionMethod($instance, $method);

        return $this->invokeReflectedMethod($reflector, $instance, get_class($instance), $parameters);
    }

    /**
     * @param string $class
     * @param string $method
     * @param array $parameters
     *
     * @return mixed
     */
    protected function callClassMethod($class, $method, $parameters)
    {
        $reflector = new \ReflectionMethod($class, $method);

        if ($reflector->isStatic()) {
            return $this->invokeReflectedMethod($reflector, null, $class, $parameters);
        }

        $instance = $this->make($class);

        return $this->invokeReflectedMethod($reflector, $instance, $class, $parameters);
    }

    /**
     * @param \ReflectionMethod $reflector
     * @param object|null $instanceOrNull
     * @param string $buildStackName
     * @param array $parameters
     *
     * @return mixed
     */
    protected function invokeReflectedMethod($reflector, $instanceOrNull, $buildStackName, $parameters)
    {
        $this->buildStack[] = $buildStackName;

        try {
            $dependencies = $this->resolveDependencies($reflector->getParameters(), $parameters);
        } catch (\Exception $e) {
            array_pop($this->buildStack);

            throw $e;
        } catch (\Throwable $e) {
            array_pop($this->buildStack);

            throw $e;
        }

        array_pop($this->buildStack);

        return $reflector->invokeArgs($instanceOrNull, $dependencies);
    }

    /**
     * @param \Closure|string $callback
     * @param array $parameters
     *
     * @return mixed
     */
    protected function callFunction($callback, $parameters)
    {
        $reflector = new \ReflectionFunction($callback);

        $dependencies = $this->resolveDependencies($reflector->getParameters(), $parameters);

        return call_user_func_array($callback, $dependencies);
    }

    /*
    |--------------------------------------------------------------------------
    | Tags
    |--------------------------------------------------------------------------
    */

    /**
     * @param string|array $abstracts
     * @param array|mixed $tags
     *
     * @return void
     */
    public function tag($abstracts, $tags)
    {
        $tags = is_array($tags) ? $tags : array_slice(func_get_args(), 1);

        foreach ($tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = array();
            }

            foreach ((array) $abstracts as $abstract) {
                $this->tags[$tag][] = $abstract;
            }
        }
    }

    /**
     * @param string $tag
     *
     * @return array
     */
    public function tagged($tag)
    {
        if (!isset($this->tags[$tag])) {
            return array();
        }

        $results = array();

        foreach ($this->tags[$tag] as $abstract) {
            $results[] = $this->make($abstract);
        }

        return $results;
    }

    /*
    |--------------------------------------------------------------------------
    | Resolving callbacks
    |--------------------------------------------------------------------------
    */

    /**
     * @param string|\Closure $abstract
     * @param \Closure|null $callback
     *
     * @return static
     */
    public function resolving($abstract, $callback = null)
    {
        if (is_string($abstract)) {
            $abstract = $this->getAlias($abstract);
        }

        if (is_null($callback) && $abstract instanceof \Closure) {
            $this->globalResolvingCallbacks[] = $abstract;
        } else {
            $this->resolvingCallbacks[$abstract][] = $callback;
        }

        return $this;
    }

    /**
     * @param string|\Closure $abstract
     * @param \Closure|null $callback
     *
     * @return static
     */
    public function afterResolving($abstract, $callback = null)
    {
        if (is_string($abstract)) {
            $abstract = $this->getAlias($abstract);
        }

        if (is_null($callback) && $abstract instanceof \Closure) {
            $this->globalAfterResolvingCallbacks[] = $abstract;
        } else {
            $this->afterResolvingCallbacks[$abstract][] = $callback;
        }

        return $this;
    }

    /**
     * @param string $abstract
     * @param mixed $object
     *
     * @return void
     */
    protected function fireResolvingCallbacks($abstract, $object)
    {
        $this->fireCallbackArray($object, $this->globalResolvingCallbacks);

        $this->fireCallbackArray($object, $this->getCallbacksForType($abstract, $object, $this->resolvingCallbacks));

        $this->fireAfterResolvingCallbacks($abstract, $object);
    }

    /**
     * @param string $abstract
     * @param mixed $object
     *
     * @return void
     */
    protected function fireAfterResolvingCallbacks($abstract, $object)
    {
        $this->fireCallbackArray($object, $this->globalAfterResolvingCallbacks);

        $this->fireCallbackArray($object, $this->getCallbacksForType($abstract, $object, $this->afterResolvingCallbacks));
    }

    /**
     * @param mixed $object
     * @param array $callbacks
     *
     * @return void
     */
    protected function fireCallbackArray($object, $callbacks)
    {
        foreach ($callbacks as $callback) {
            call_user_func($callback, $object, $this);
        }
    }

    /**
     * @param string $abstract
     * @param mixed $object
     * @param array $callbacksPerType
     *
     * @return array
     */
    protected function getCallbacksForType($abstract, $object, $callbacksPerType)
    {
        $results = array();

        foreach ($callbacksPerType as $type => $callbacks) {
            if ($type === $abstract || $object instanceof $type) {
                $results = array_merge($results, $callbacks);
            }
        }

        return $results;
    }

    /*
    |--------------------------------------------------------------------------
    | Introspection / lifecycle
    |--------------------------------------------------------------------------
    */

    /**
     * @param string $abstract
     *
     * @return bool
     */
    public function bound($abstract)
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]) || $this->isAlias($abstract);
    }

    /**
     * @param string $abstract
     *
     * @return bool
     */
    public function resolved($abstract)
    {
        if ($this->isAlias($abstract)) {
            $abstract = $this->getAlias($abstract);
        }

        return isset($this->resolved[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * PSR-11.
     *
     * @param string $id
     *
     * @return bool
     */
    public function has($id)
    {
        return $this->bound($id);
    }

    /**
     * PSR-11.
     *
     * @param string $id
     *
     * @return mixed
     */
    public function get($id)
    {
        try {
            return $this->make($id);
        } catch (Exceptions\ContainerException $e) {
            if ($this->bound($id)) {
                throw $e;
            }

            throw new Exceptions\NotFoundException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Remove a resolved instance from the instance cache.
     *
     * @param string $abstract
     *
     * @return static
     */
    public function forgetInstance($abstract)
    {
        unset($this->instances[$abstract]);

        return $this;
    }

    /**
     * Clear all of the instances from the container.
     *
     * @return static
     */
    public function forgetInstances()
    {
        $this->instances = array();

        return $this;
    }

    /**
     * Clear all of the scoped instances from the container.
     *
     * @return static
     */
    public function forgetScopedInstances()
    {
        foreach ($this->scopedInstances as $scoped) {
            $this->forgetInstance($scoped);
        }

        return $this;
    }

    /**
     * Flush the container of all bindings and resolved instances.
     *
     * @return static
     */
    public function flush()
    {
        $this->aliases = array();
        $this->abstractAliases = array();
        $this->resolved = array();
        $this->bindings = array();
        $this->instances = array();
        $this->scopedInstances = array();
        $this->contextual = array();
        $this->buildStack = array();
        $this->with = array();
        $this->extenders = array();
        $this->tags = array();
        $this->globalResolvingCallbacks = array();
        $this->resolvingCallbacks = array();
        $this->globalAfterResolvingCallbacks = array();
        $this->afterResolvingCallbacks = array();

        $this->instance(__CLASS__, $this);

        $this->instance(get_class($this), $this);

        return $this;
    }

    /**
     * Get the container instance.
     *
     * @return static
     */
    public static function getInstance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Set the shared container instance.
     *
     * @param \Wilkques\Container\Container|null $container
     *
     * @return \Wilkques\Container\Container|null
     */
    public static function setInstance(Container $container = null)
    {
        return static::$instance = $container;
    }
}
