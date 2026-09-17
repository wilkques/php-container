# Upgrading

## Upgrading to 5.0 from 4.x

Version 5.0 is a from-scratch rewrite of `Container.php` that fixes several
latent bugs and closes the gap with `Illuminate\Container\Container`
semantics. Most application code keeps working unchanged, but a handful of
behaviors changed in ways that can be observable. Read through the list
below before upgrading.

### 1. `bind()`/`singleton()`/`scoped()` are now genuinely lazy

In 4.x, `bind()` ran its factory immediately the first time the abstract was
resolved and then **cached the result and reused it forever after** —
functionally, `bind()` behaved like `singleton()`. In 5.0, `bind()` is a true
transient factory: the closure runs **fresh on every single `make()`/`get()`
call**, and nothing is cached.

```php
// Before (4.x) — factory ran once, same instance returned every time:
container()->bind('logger', function () {
    return new FileLogger;
});

container()->make('logger') === container()->make('logger'); // true in 4.x

// After (5.0) — factory runs on every make():
container()->make('logger') === container()->make('logger'); // false in 5.0
```

If you relied on `bind()`'s old caching behavior, switch to `singleton()`
(or `scoped()` if you also want it clearable via
`forgetScopedInstances()`):

```php
container()->singleton('logger', function () {
    return new FileLogger;
});

container()->make('logger') === container()->make('logger'); // true, as before
```

### 2. `get($abstract)` now throws instead of returning `null`

Two distinct changes here:

**(a) Unbound and unresolvable → throws, doesn't return `null`.**

```php
// Before (4.x):
$value = container()->get('nope'); // null

// After (5.0):
$value = container()->get('nope'); // throws Psr\Container\NotFoundExceptionInterface
```

If you were using the `null` return as an existence check, use `has()`
instead (it always did, and still does, return a plain boolean):

```php
if (container()->has('nope')) {
    $value = container()->get('nope');
}
```

If you want to keep a single expression and tolerate the exception instead,
catch `Psr\Container\NotFoundExceptionInterface` around the `get()` call.

**(b) Unbound-but-autowirable class names now succeed instead of returning `null`.**

If `$abstract` is a real, instantiable class name that simply has no
explicit binding, 4.x's `get()` still returned `null` for it. In 5.0, `get()`
(via `make()`) will autowire and construct it successfully:

```php
class Foo {}

// Before (4.x): container()->get(Foo::class) === null, even though Foo is a
// perfectly constructible class with no dependencies.

// After (5.0): container()->get(Foo::class) returns a real Foo instance.
```

This is generally a improvement (it matches `make()`'s existing behavior),
but if any code specifically depended on such classes resolving to `null`
via `get()`, that code must be updated.

### 3. `make()` no longer permanently caches non-shared builds

In 4.x, **every** `make()` call — even for a plain `bind()` (non-shared)
binding — wrote its result into the internal instance cache. This meant
memory grew unbounded with repeated `make()` calls, and it also meant a
previous `make()`'s result could be returned by a later, unrelated call.
In 5.0, only `singleton()`/`scoped()` bindings (and `instance()`/`register()`
registrations) are cached; plain `bind()` bindings are never written to the
instance cache, no matter how many times they're resolved.

```php
container()->bind('thing', function () { return new Dep; });

for ($i = 0; $i < 1000; $i++) {
    container()->make('thing');
}
// 4.x: 1000 cached (leaked) entries accumulate internally.
// 5.0: nothing is cached; each call is independent.
```

If any code relied on a plain `bind()` abstract "sticking" to whatever the
last `make()` built (i.e. treating an unintentionally-cached `bind()` like a
singleton), switch that binding to `singleton()` explicitly — don't rely on
the old caching side effect.

### 4. `call([$object, 'method'])` now uses the exact object you passed in

In 4.x, `call([$object, 'method'])` **discarded** the `$object` you passed
and silently built a *new* instance of `get_class($object)` through the
container — which could also clobber/overwrite an existing singleton
registration for that class — and then invoked `method` on that new
instance instead of yours. In 5.0, `call()` invokes `method` directly on the
exact `$object` reference you passed in; nothing is rebuilt or re-resolved.

```php
$counter = new Counter; // constructs the object once

container()->call([$counter, 'whoAmI']);
// 4.x: invoked on a brand-new Counter built by the container (constructing
//      a 2nd instance), NOT on $counter.
// 5.0: invoked on $counter itself. No extra instance is constructed.
```

This is a bug fix, but it's an observable behavior change if any code
depended on the old (incorrect) rebuild-a-fresh-instance behavior — for
example, if a constructor's side effects (e.g. a counter increment) were
being relied on to fire again on every `call()`.

### 5. Union-typed constructor parameters are now supported

4.x always threw `InvalidArgumentException: Union type function signatures
are not supported.` for any constructor parameter with a PHP 8 union type
(e.g. `int|string $x`). 5.0 resolves union types: a nullable union with no
matching class member and no default resolves to `null` if allowed, a
typed class member is autowired if present, and non-nullable/no-default
unions with no resolvable class member throw
`Wilkques\Container\Exceptions\BindingResolutionException` instead.

```php
class UnionCtor {
    public function __construct(?Dep $d, int|string|null $u = 138) {}
}

// Before (4.x): container()->make(UnionCtor::class) always threw
//     InvalidArgumentException('Union type function signatures are not supported.')
//     regardless of the actual parameter types.

// After (5.0): resolves normally — $d is autowired, $u falls back to its default (138).
```

If any code was specifically catching `InvalidArgumentException` around
`make()`/`call()` as a workaround for this limitation, that catch block is
now dead code — the exception no longer occurs for this reason. (If you were
catching `InvalidArgumentException` broadly for other reasons, see item 7
below.)

### 6. `flush()` now returns `$this` instead of `void`

Purely additive/widening — existing code that ignores the return value is
unaffected.

```php
// Before (4.x):
container()->flush(); // void

// After (5.0):
container()->flush()->make('logger'); // chaining now works
```

Additionally, 5.0's `flush()` re-registers the container's own self-binding
(`Container::class` and `get_class($this)`) immediately after clearing
everything, so `container()->make(Container::class)` keeps working right
after a flush. In 4.x this self-binding was lost after `flush()` (a latent
bug) and had to be manually re-established.

### 7. Exceptions are now typed, not generic `InvalidArgumentException`

4.x's resolution failures generally surfaced as a plain
`\InvalidArgumentException`. 5.0 introduces a proper exception hierarchy
under `Wilkques\Container\Exceptions`, all implementing the relevant PSR-11
interface:

- `Wilkques\Container\Exceptions\ContainerException` (implements
  `Psr\Container\ContainerExceptionInterface`)
- `Wilkques\Container\Exceptions\BindingResolutionException extends ContainerException`
- `Wilkques\Container\Exceptions\CircularDependencyException extends BindingResolutionException`
- `Wilkques\Container\Exceptions\NotFoundException extends ContainerException implements Psr\Container\NotFoundExceptionInterface`

If your code catches `\InvalidArgumentException` around container calls,
update it to catch `Psr\Container\ContainerExceptionInterface` /
`Psr\Container\NotFoundExceptionInterface` (for PSR-11-portable code), or the
concrete `Wilkques\Container\Exceptions\*` classes directly (if you need to
distinguish, e.g., a circular dependency from a plain resolution failure).

```php
// Before (4.x):
try {
    container()->make(SomeInterface::class);
} catch (\InvalidArgumentException $e) {
    // ...
}

// After (5.0):
try {
    container()->make(SomeInterface::class);
} catch (\Wilkques\Container\Exceptions\BindingResolutionException $e) {
    // ...
}
```

### 8. `container()` helper's parameter renamed from `$abstruct` to `$abstract`

The global `container()` helper function's first parameter had a typo
(`$abstruct`) in 4.x; it's spelled correctly (`$abstract`) in 5.0. This only
matters if you were calling the helper with a named argument (PHP 8.0+),
e.g. `container(abstruct: Foo::class)` — extremely unlikely, but rename the
named argument to `abstract` if you did this.

### 9. New: circular dependencies now throw instead of exhausting memory

Not a breaking change, but a newly-observable one: if two or more classes
depend on each other in a cycle (directly, or through a chain of
dependencies), 4.x had no cycle detection and would recurse until PHP's
memory limit was exhausted — an uncatchable fatal error that could take down
an entire process. 5.0 detects the cycle and throws
`Wilkques\Container\Exceptions\CircularDependencyException` with a message
describing the full build chain (e.g. `Circular dependency detected while
building [CircularA] -> [CircularB] -> [CircularA].`). If your application
had an undetected cycle that happened to "work" by never actually being
resolved, upgrading may surface it for the first time as a catchable
exception instead of a fatal crash.

### Other changes verified, not expected to break anything

- **Contextual binding by type name is now supported.** 4.x's
  `when($concrete)->needs($needle)->give(...)` only ever matched `$needle`
  against a `'$paramName'`-style parameter name; a `$needle` that was a
  class/interface name was silently never matched. 5.0 checks the
  parameter-name form first and now additionally falls back to matching by
  the dependency's declared type name. This is purely additive — existing
  parameter-name-based contextual bindings behave exactly as before.
- **`register()`'s batch (array-of-pairs) form is fixed.** In 4.x, calling
  `register()` with an array of `[abstract, concrete]` pairs did not work
  correctly. In 5.0 it works as documented in the README. If you were
  avoiding the batch form because of this, it's now safe to use.
- No other breaking changes to public method signatures were found while
  auditing `resolveAbstract()`/`bindAbstract()`/`instance()`/`alias()` and
  the rest of the public API against the current `src/Container.php` — those
  methods' signatures and semantics are unchanged from 4.x.
