# Shared Utilities Reference

This is a quick-reference for reusable utilities that appear across multiple domains. Use it as a lookup page, not a tutorial.

## `AbstractCastableObject`

**Location:** `App\Utils\Casts\AbstractCastableObject`

Reflection-based base class for typed, serializable PHP objects that are hydrated from and persisted to flat string maps (database rows, config entries, environment files).

Extend it and declare `public` properties with type hints. Scalar types, arrays, enums, Carbon dates, encrypted values, and nested castable objects are all handled automatically. Add a `#[CastedValue]` attribute only when automatic inference is not enough.

```php
class MyConfig extends AbstractCastableObject
{
    public int $max_tokens = 4096;
    public bool $stream = true;

    #[CastedValue('encrypted:string')]
    public string $api_key = '';
}

$config = MyConfig::fromStringArray(['max_tokens' => '8192', 'stream' => '0', 'api_key' => '<ciphertext>']);
$config->max_tokens; // int(8192)

$row = $config->toStringArray(); // back to strings for persistence
```

**Supported cast types at a glance:**

| Category | Types |
|---|---|
| Primitive | `int`, `float`, `bool`, `string` |
| Structured | `array` / `json` (JSON string ↔ array), `object` (JSON string ↔ stdClass) |
| Encrypted | `encrypted:string`, `encrypted:array`, `encrypted:object` |
| Date/time | `date`, `datetime`, `datetime:FORMAT`, `immutable_date`, `immutable_datetime`, `timestamp` |
| Nested castable | Any subclass of `AbstractCastableObject` — stored as JSON string |
| Enum | Auto-detected from type hint; `BackedEnum` by value, `UnitEnum` by case name |
| Custom | Any class implementing `CastsValue` — use fully-qualified class name |

The cast map is derived via reflection and cached statically per concrete class. No repeated overhead after the first call.

`AbstractCastableObject` is the base for `AbstractConfig` (public config blocks) and all typed provider-settings value objects.

## Eloquent casts

These live in `App\Casts\` and are applied in Eloquent model `$casts` arrays.

| Cast class | Purpose |
|---|---|
| `AsInstance` | Generic cast for any class implementing `CastableInstanceInterface` (`fromArray()` / `toArray()`). Used widely for structured value objects on models. |
| `AsLocale` | Casts a database string to a `Locale` value object via `LocaleService::getMostLikelyLocale()`. |
| `AsAsymmetricPublicKeyCast` | Transparent asymmetric public-key encryption/decryption on a model attribute. |
| `AsHybridCryptoValueCast` | Transparent hybrid encryption/decryption (random AES key + asymmetric wrapping). |
| `AsSymmetricCryptoValueCast` | Transparent symmetric AES-GCM encryption/decryption. |

The crypto casts are documented in detail in [Encryption Overview](../800-Encryption-and-Security/index.md).

## `RecursiveMerger` / `Arr::mergeRecursive()`

**Location:** `App\Utils\Arrays\RecursiveMerger`

Deep array merge with configurable behaviour. Registered as an `Arr::mergeRecursive()` macro in `AppServiceProvider`. Supports unsetting keys in the merged result. Used wherever configuration layers need to be combined without the PHP `array_merge_recursive` quirk of concatenating duplicate scalar keys.

## `LazySingletonList`

**Location:** `App\Utils\Lists\LazySingletonList`

A keyed instance cache that creates each value on first access via a factory closure and reuses it on subsequent calls. Backed by a key-generator closure that maps any input (including complex objects) to a string storage key.

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

**Location:** `App\Utils\Sorting\IntuitiveTopSorter`

Topological sorter with cycle detection. The "intuitive" part: the **anchor** item stays in place, the **dependent** item moves. Standard topological sort moves the pivot; this reverses that, which is what the call-site intent normally implies.

```php
$sorter = new IntuitiveTopSorter(['a', 'b', 'c']);
$sorter->moveItemAfter('a', 'c');  // a moves after c; c stays
$result = $sorter->sort();         // ['b', 'c', 'a']
```

Throws `CyclicDependencyException` when constraints form a cycle.

Used by `AgentRegistry` for `before:`/`after:` factory ordering. Will be used by `PluginRegistry` for load-order resolution.

## Infrastructure macros

Two macros live canonically in [Infrastructure](../1000-Infrastructure/index.md). Their full documentation is there; brief references follow.

**`Http::getSsrfSafe(url)`** — registered by `SsrfSafeGetterMacro`. Validates every URL and redirect hop against a public-IP allowlist before making the request. All external HTTP calls in HAWKI must use this instead of `Http::get()`.

**`Schedule::commandWithDynamicInterval()`** — registered by `ScheduleWithDynamicIntervalFactory`. Reads the command's scheduling frequency from DB/config rather than hardcoding a cron expression. The `never` sentinel disables a job without removing it from the schedule definition.
