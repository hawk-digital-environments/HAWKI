# Shared Utilities

Reusable utilities that appear across multiple domains. Use this as a lookup page; open the classes for the canonical signatures. For Eloquent casts, see [Eloquent Casts](../200-Concepts/160-Model-Casts/100-Eloquent-Casts.md); for `AbstractCastableObject`, see [Castable Objects](../200-Concepts/160-Model-Casts/200-Castable-Objects.md).

## `RecursiveMerger` / `Arr::mergeRecursive()`

`App\Utils\Arrays\RecursiveMerger` — deep array merge with configurable behaviour. Registered as an `Arr::mergeRecursive()` macro in `AppServiceProvider`. Supports unsetting keys in the merged result. Used wherever configuration layers need to be combined without the PHP `array_merge_recursive` quirk of concatenating duplicate scalar keys.

## `LazySingletonList`

`App\Utils\Lists\LazySingletonList` — a keyed instance cache that creates each value on first access via a factory closure and reuses it on subsequent calls. Backed by a key-generator closure that maps any input (including complex objects) to a string storage key.

```php
$adapters = new LazySingletonList(
    keyGenerator: fn(string $key) => $key,
    factory:      fn(string $key) => $this->container->make($adapterClasses[$key]),
);

$adapter = $adapters->get('openai'); // created on first call
$adapter === $adapters->get('openai'); // same instance
```

Used by `ProviderAdapterRegistry` and `AgentRegistry` for lazy container-resolved singleton maps. Plugin-aware registries will reuse the same pattern.

## `IntuitiveTopSorter`

`App\Utils\Sorting\IntuitiveTopSorter` — topological sorter with cycle detection. The "intuitive" part: the **anchor** item stays in place, the **dependent** item moves. Standard topological sort moves the pivot; this reverses that, which is what the call-site intent normally implies.

```php
$sorter = new IntuitiveTopSorter(['a', 'b', 'c']);
$sorter->moveItemAfter('a', 'c');  // a moves after c; c stays
$result = $sorter->sort();         // ['b', 'c', 'a']
```

Throws `CyclicDependencyException` when constraints form a cycle. Used by `AgentRegistry` for `before:`/`after:` factory ordering. Will be used by `PluginRegistry` for load-order resolution.

## Infrastructure macros

Two macros live canonically in [Infrastructure](../600-Infrastructure/index.md):

- **`Http::getSsrfSafe(url)`** — registered by `SsrfSafeGetterMacro`. Validates every URL and redirect hop against a public-IP allowlist before making the request. All external HTTP calls in HAWKI must use this instead of `Http::get()`. See [SSRF Protection](../600-Infrastructure/300-SSRF-Protection.md).
- **`Schedule::commandWithDynamicInterval()`** — registered by `ScheduleWithDynamicIntervalFactory`. Reads the command's scheduling frequency from DB/config rather than hardcoding a cron expression. The `never` sentinel disables a job without removing it from the schedule definition. See [Scheduling](../600-Infrastructure/200-Scheduling.md).
