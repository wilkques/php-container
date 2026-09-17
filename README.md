# Container

[![Latest Stable Version](https://poser.pugx.org/wilkques/container/v/stable)](https://packagist.org/packages/wilkques/container)
[![License](https://poser.pugx.org/wilkques/container/license)](https://packagist.org/packages/wilkques/container)

English | [繁體中文](README_ZH.md)

A small, dependency-free, PSR-11 compatible dependency injection container,
aligned with `Illuminate\Container\Container` semantics where practical, kept
compatible back to PHP 5.3.

> **Upgrading from 4.x?** Several methods changed behavior in 5.0, most
> notably `bind()` and `get()`. Read [UPGRADING.md](UPGRADING.md) before you
> upgrade.

## How to use

```
composer require wilkques/container
```

That's it — no manual `require` of any file is needed. Composer's `files`
autoload mechanism (declared in this package's `composer.json`) loads
`src/helpers.php` automatically, which defines the global `container()`
helper used throughout this document and registers the bundled PSR-11
interfaces if the real `psr/container` package isn't already installed (see
[PSR-11](#psr-11) below).

## Method

1. `register`

    Registers an **already-built object** into the container under the given
    name. It is a thin wrapper around [`instance()`](#5-instance) — the
    object you pass is stored as-is and returned as-is on every subsequent
    resolution (it is effectively always "shared"). Both the single-pair form
    and the batch array-of-pairs form are supported:

    ```php
    container()->register(
        '<your class name>',
        new \Your\Class\Name
    );

    // or, a batch of [abstract, concrete] pairs

    container()->register([
        [
            '<your class name1>',
            new \Your\Class\Name1
        ],
        [
            '<your class name2>',
            new \Your\Class\Name2
        ],

        // ...
    ]);
    ```

1. `bind($abstract, $concrete = null, $shared = false)`

    Registers a **lazy, non-shared factory**. The `$concrete` closure (or
    class name) is not invoked when you call `bind()` — it is only invoked
    when the abstract is resolved, and it is invoked **fresh on every single
    `make()`/`get()` call**, returning a new instance each time.

    The closure is always called as `$concrete($container, $parameters)` —
    its first parameter receives **the container itself**, no matter what
    (if anything) it's type-hinted as. It is *not* autowired by type hint;
    resolve any dependencies you need explicitly inside the closure body.
    This applies identically to [`singleton()`](#3-singleton) and
    [`scoped()`](#4-scoped) below, since both are built on top of `bind()`:

    ```php
    container()->bind(\Your\Class\Config::class, function ($container) {
        $filesystem = $container->make(\Your\Class\Filesystem::class);

        return new \Your\Class\Config($filesystem);
    });
    ```

    > **Behavior change from v4:** in 4.x, a binding closure's own parameters
    > were autowired by type hint (the same mechanism `call()` uses). See
    > [UPGRADING.md](UPGRADING.md#10-binding-closures-no-longer-autowire-their-own-parameters-by-type-hint).

    > **Behavior change from v4:** in 4.x, `bind()` behaved like a singleton
    > — the factory ran once and the same instance was cached and returned
    > forever after. In 5.0, `bind()` is a true transient/factory binding. If
    > you want the old caching behavior, use [`singleton()`](#3-singleton)
    > instead. See [UPGRADING.md](UPGRADING.md#1-bindsingletonscoped-are-now-genuinely-lazy).

    ```php
    container()->bind('<your class name>', function ($container) {
        return new \Your\Class\Name;
    });

    container()->make('<your class name>'); // new instance
    container()->make('<your class name>'); // a different, new instance
    ```

1. `singleton($abstract, $concrete = null)`

    Binds a class or interface into the container that should only be
    resolved one time. Once a singleton binding is resolved, the same object
    instance is returned on every subsequent call into the container:

    ```php
    container()->singleton('<your class name>', function ($container) {
        return new \Your\Class\Name;
    });

    container()->make('<your class name>') === container()->make('<your class name>'); // true
    ```

1. `scoped($abstract, $concrete = null)`

    Behaves exactly like `singleton()`, but the binding is additionally
    tracked so it can be cleared in bulk with
    [`forgetScopedInstances()`](#15-forgetinstance-forgetinstances-forgetscopedinstances-flush) —
    handy for per-request state in long-running processes.

    ```php
    container()->scoped('<your class name>', function ($container) {
        return new \Your\Class\Name;
    });
    ```

1. `instance($abstract, $instance)`

    The direct, explicit way to register an already-built object as a shared
    instance — this is what `register()` and `singleton()`'s resolved cache
    both end up calling under the hood. Returns the instance you passed in.

    ```php
    $object = new \Your\Class\Name;

    container()->instance('<your class name>', $object);

    container()->make('<your class name>') === $object; // true
    ```

1. `alias($abstract, $alias)`

    Registers an alternate name for an existing abstract. Aliases chain (an
    alias may itself be aliased) and are resolved transparently by
    `make()`/`get()`/`has()`. Aliasing an abstract to itself throws a
    `LogicException`.

    ```php
    container()->instance('<your class name>', new \Your\Class\Name);

    container()->alias('<your class name>', '<your class alias>');

    container()->make('<your class alias>'); // same object as '<your class name>'
    ```

1. `tag($abstracts, $tags)` / `tagged($tag)`

    Groups one or more abstracts under one or more tags, and later resolves
    every abstract registered under a tag in one call. `$tags` may be a
    single tag string, an array of tag strings, or extra variadic string
    arguments. `tagged()` **eagerly resolves every tagged abstract and
    returns a plain PHP array** — never a generator, since this library
    targets PHP 5.3, which has no generator support.

    ```php
    container()->bind('report.csv', function () { return new \Reports\Csv; });
    container()->bind('report.pdf', function () { return new \Reports\Pdf; });

    container()->tag(['report.csv', 'report.pdf'], 'reports');

    foreach (container()->tagged('reports') as $report) {
        // $report is a resolved \Reports\Csv / \Reports\Pdf instance
    }
    ```

1. `extend($abstract, \Closure $closure)`

    Registers a decorator that wraps/modifies an abstract's resolved
    instance. If the abstract has already been resolved (e.g. it's a
    singleton that was already built), the closure is applied immediately to
    the cached instance; otherwise, it's applied the moment the abstract is
    next built. Multiple extenders on the same abstract are applied in
    registration order.

    ```php
    container()->bind('<your class name>', function () {
        return new \Your\Class\Name;
    });

    container()->extend('<your class name>', function ($instance, $container) {
        return new \Your\Class\Decorator($instance);
    });

    container()->make('<your class name>'); // a \Your\Class\Decorator wrapping \Your\Class\Name
    ```

1. `resolving($abstract, $callback = null)` / `afterResolving($abstract, $callback = null)`

    Register lifecycle hooks that run whenever the container resolves an
    entry — `resolving()` runs right after the object is built (before it's
    cached/returned), `afterResolving()` runs right after that. Both support
    two forms:

    - **Global form** — pass only a `\Closure`, no abstract, and it fires for
      *every* resolution:

        ```php
        container()->resolving(function ($object, $container) {
            // runs for every resolved object
        });
        ```

    - **Per-abstract form** — pass an abstract name plus a callback, and it
      fires only when that abstract (or an instance of that type) is
      resolved:

        ```php
        container()->resolving('<your class name>', function ($object, $container) {
            // runs only when '<your class name>' is resolved
        });
        ```

    Both `resolving()` and `afterResolving()` return `$this` for chaining.
    For a single resolution, callbacks fire in this order: global
    `resolving()` callbacks, then per-abstract `resolving()` callbacks, then
    global `afterResolving()` callbacks, then per-abstract `afterResolving()`
    callbacks.

1. `when($concrete)->needs($abstractOrParamName)->give($implementation)`

    Contextual binding: override what a *specific* concrete class receives
    for one of its constructor dependencies, without changing the global
    binding for that dependency. `$implementation` may be a `\Closure`
    (invoked with the container), a class/interface name (resolved through
    the container), or any other value (returned as-is) — including `null`,
    `0`, or `''`, all of which are honored as explicit values.

    Two forms of `needs()` are supported, checked in this order whenever the
    dependency is about to be resolved:

    1. **By parameter name** — `needs('$paramName')` (note the leading `$`).
       This is checked first.
    2. **By type name** — `needs(SomeInterface::class)`, matching the
       dependency's declared class/interface type hint. This is checked if
       no parameter-name binding matched.

    ```php
    // By parameter name:
    container()->when(\Your\Class\Name::class)
        ->needs('$connection')
        ->give('mysql');

    // By type name:
    container()->when(\Your\Class\Name::class)
        ->needs(\Your\Contract\LoggerInterface::class)
        ->give(\Your\Class\FileLogger::class);

    container()->make(\Your\Class\Name::class);
    ```

1. `has($abstract)`

    PSR-11 `ContainerInterface::has()`. Returns `true` if the container has a
    binding, a registered instance, or a resolvable alias for `$abstract`.
    Does **not** guarantee `get()`/`make()` will succeed without throwing
    (a bound factory can still fail at build time).

    ```php
    container()->has('<your class name>');
    ```

1. `get($abstract)`

    PSR-11 `ContainerInterface::get()`. Resolves and returns the entry.

    - Throws `Psr\Container\NotFoundExceptionInterface` (a
      `Wilkques\Container\Exceptions\NotFoundException`) if `$abstract` is
      not bound/registered and cannot be autowired (e.g. an unknown class
      name, or an interface/abstract class with no binding).
    - Throws `Psr\Container\ContainerExceptionInterface` (a
      `Wilkques\Container\Exceptions\ContainerException`, typically a
      `BindingResolutionException` or `CircularDependencyException`) if
      `$abstract` *is* bound but building it fails for some other reason
      (e.g. its factory throws, or a dependency can't be resolved).

    > **Behavior change from v4:** in 4.x, `get()` returned `null` for
    > anything it couldn't resolve, instead of throwing. See
    > [UPGRADING.md](UPGRADING.md#2-getabstract-now-throws-instead-of-returning-null).

    ```php
    container()->get('<your class name>');
    ```

1. `make($abstract, $arguments = array())`

    Resolves `$abstract`, building it (with constructor autowiring) if
    needed. `$arguments` lets you override specific constructor parameters,
    by name or by position — any keys in `$arguments` that don't correspond
    to an actual constructor parameter are simply ignored (not passed
    through as stray positional arguments).

    ```php
    container('<your class name>');

    // or

    container()->make('<your class name>');

    // override the 2nd constructor parameter by position
    container()->make('<your class name>', [1 => 'value']);

    // override a constructor parameter by name
    container()->make('<your class name>', ['paramName' => 'value']);
    ```

1. `call($callable, $arguments = array())`

    Calls the given callable, autowiring any parameters not present in
    `$arguments`. Supported forms:

    - `[$object, 'method']` — calls `method` on **exactly the `$object`
      instance you passed in** (it is never rebuilt/re-resolved through the
      container, even if a singleton for that class already exists).
    - `['ClassName', 'method']` — if `method` is `static`, it's invoked
      directly with no instance constructed at all; otherwise, an instance
      of `ClassName` is built via `make()` (so an existing singleton binding
      is honored) and `method` is invoked on it.
    - `'ClassName@method'` and `'ClassName::method'` — both parsed into the
      `['ClassName', 'method']` form above and behave identically to it
      (including the static-vs-instance distinction — `'::'` does **not**
      imply the method must be static).
    - A `\Closure`.
    - An invokable object (any object with `__invoke()`, that isn't itself a
      `\Closure`) — its `__invoke()` method is called.

    ```php
    container()->call(['<your class name>', '<your class method name>'], ['<your class method vars name>' => '<your class method vars value>']);

    // or, on a specific already-built instance

    container()->call([new \Your\Class\Name, '<your class method name>'], ['<your class method vars name>' => '<your class method vars value>']);

    // or

    container()->call('\Your\Class\Name@<your class method name>');

    // or

    container()->call('\Your\Class\Name::<your class method name>');

    // or

    container()->call(function (\Your\Class\Name $abstract) {
        // do something
    });
    ```

1. `forgetInstance($abstract)`, `forgetInstances()`, `forgetScopedInstances()`, `flush()`

    - `forgetInstance($abstract)` — removes a single resolved instance from
      the instance cache.

        ```php
        container()->forgetInstance('<your class name>');
        ```

    - `forgetInstances()` — clears every resolved instance from the
      container.

    - `forgetScopedInstances()` — clears only the instances registered via
      `scoped()`.

    - `flush()` — resets the container to a fresh state: every binding,
      alias, tag, extender, resolving callback, and resolved instance is
      cleared, and the container then re-registers itself (so
      `container()->make(Container::class)` keeps working immediately after
      a flush). `flush()` returns `$this` for chaining.

      > **Behavior change from v4:** `flush()` used to return `void`. It now
      > returns `$this`, and it re-registers the container's own
      > self-binding (v4 lost it after a flush).

## Exceptions

All container-specific exceptions live under `Wilkques\Container\Exceptions`:

- `ContainerException extends \Exception implements Psr\Container\ContainerExceptionInterface`
  — the base exception for any container error.
- `BindingResolutionException extends ContainerException`
  — thrown when the container cannot build/resolve a binding: the target
  class doesn't exist, isn't instantiable (interface/abstract class with no
  binding), or one of its dependencies can't be resolved.
- `CircularDependencyException extends BindingResolutionException`
  — thrown when resolving an abstract would require resolving itself again
  further down the build stack (directly or through a chain of
  dependencies), instead of exhausting memory.
- `NotFoundException extends ContainerException implements Psr\Container\NotFoundExceptionInterface`
  — thrown only by `get()`, only when `$abstract` is not bound/registered
  and cannot be autowired (the PSR-11 "no entry for this identifier" case).

## PSR-11

`Wilkques\Container\Container implements Psr\Container\ContainerInterface`.

Because this library must keep working on PHP 5.3, it cannot depend on a
`psr/container` release that requires PHP >= 7.2. `composer.json` therefore
pins `psr/container: >=1.0 <1.1`: PSR-11 1.1+ adds a `: bool` return type to
`ContainerInterface::has()`, which a PHP-5.3-compatible class cannot declare.
If your application already requires a real `psr/container` package, this
library uses it as-is (`provide: psr/container-implementation: 1.0`); if not,
`src/helpers.php` defines the `Psr\Container\ContainerInterface`,
`ContainerExceptionInterface`, and `NotFoundExceptionInterface` interfaces
itself (only if they don't already exist), so the container is always
PSR-11-typed regardless of what else is installed.

## PHP compatibility

Targets `php >= 5.3` (see `composer.json`). Verified:

- **PHP 5.3.29** — every file under `src/` parses with `php -l` (no short
  arrays, `finally`, `??`, `::class`, or type declarations are used).
- **PHP 7.0.33** — this is the version most likely to break, since
  `ReflectionNamedType::getName()` did not exist until PHP 7.1 and
  `ReflectionType` had only `__toString()`. The container guards this with
  `method_exists($type, 'getName')`. Verified directly: autowiring a
  class-typed parameter, a `self`-typed parameter, and a `parent`-typed
  parameter (the exact cases that exercise this code path) all resolve
  correctly, in addition to the general behavior below.
- **PHP 7.4.33** and **PHP 8.2.33** — full behavioral smoke test: interface-to-
  implementation binding, circular-dependency detection (throws
  `CircularDependencyException` instead of exhausting memory), PSR-11
  `has()`/`get()` (including `NotFoundExceptionInterface` on a missing
  binding), contextual binding by type name, `tag()`/`tagged()`, `extend()`,
  and variadic parameter injection via contextual `give()` — all pass
  identically on both versions (and on 7.0).

The full PHPUnit suite (78 tests) runs on PHP 8.2 with PHPUnit 9.6, since no
version of PHPUnit supports both PHP 5.3 and modern PHP simultaneously; PHP
5.3 compatibility of the library source itself is instead guaranteed by the
`php -l` check above, which is a stronger guarantee than a compatibility
linter for syntax (though it does not, by itself, catch runtime-only API
differences the way the 7.0-specific check above does).
