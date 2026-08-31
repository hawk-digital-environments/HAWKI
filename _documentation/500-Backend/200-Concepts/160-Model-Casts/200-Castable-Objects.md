# Castable Objects

`AbstractCastableObject` (`app/Utils/Casts/AbstractCastableObject`) is a reflection-based base class for typed, serialisable PHP objects hydrated from and persisted to flat string maps. It works completely without Eloquent models — it is a standalone serialisation tool.

## When to use it

Use `AbstractCastableObject` when you have a typed object that needs to round-trip through a flat string map (database rows, config entries, environment files) and you are not working through an Eloquent model column. Typical use cases:

- **Config blocks** — `AbstractConfig` extends `AbstractCastableObject` (see [Config Blocks](../200-Config-Blocks.md)).
- **Provider settings** — typed value objects for per-provider configuration stored in the database.
- **Database-backed configuration** — the v3 plugin system's `ConfigDb` layer will use this as its serialisation mechanism.

If you are working with an Eloquent model column, use [Eloquent Casts](./100-Eloquent-Casts.md) instead.

## How to build one

Extend `AbstractCastableObject` and declare `public` properties with type hints. Scalar types, arrays, enums, Carbon dates, encrypted values, and nested castable objects are all handled automatically via reflection.

```php
use App\Utils\Casts\AbstractCastableObject;
use App\Utils\Casts\Attributes\CastedValue;

class MyConfig extends AbstractCastableObject
{
    public int $max_tokens = 4096;
    public bool $stream = true;

    #[CastedValue('encrypted:string')]
    public string $api_key = '';
}
```

Add `#[CastedValue]` only when automatic inference is not enough.

## How to hydrate and serialise

```php
// From a flat string map (DB row, config file, env)
$config = MyConfig::fromStringArray(['max_tokens' => '8192', 'stream' => '0', 'api_key' => '<ciphertext>']);
$config->max_tokens; // int(8192)

// Back to a flat string map for persistence
$row = $config->toStringArray();
```

`fromStringArray()` hydrates; `toStringArray()` serialises. The cast map is derived via reflection and cached statically per concrete class — no repeated overhead after the first call.

## Supported cast types

| Category | Types |
|---|---|
| Primitive | `int`, `float`, `bool`, `string` |
| Structured | `array` / `json` (JSON string ↔ array), `object` (JSON string ↔ stdClass) |
| Encrypted | `encrypted:string`, `encrypted:array`, `encrypted:object` |
| Date/time | `date`, `datetime`, `datetime:FORMAT`, `immutable_date`, `immutable_datetime`, `timestamp` |
| Nested castable | Any subclass of `AbstractCastableObject` — stored as JSON string |
| Enum | Auto-detected from type hint; `BackedEnum` by value, `UnitEnum` by case name |
| Custom | Any class implementing `CastsValue` — use fully-qualified class name |

## How it differs from Eloquent casts

`AbstractCastableObject` is not a "layer" on top of Eloquent casts — it is a separate tool. Eloquent casts (`app/Casts/`) extend Laravel's `$casts` pattern and are attached to model columns. `AbstractCastableObject` is a standalone class you extend; it serialises to and from any flat string map, not just model columns. They share the `#[CastedValue]` attribute concept but the infrastructure is independent.

## Source

- `app/Utils/Casts/AbstractCastableObject.php`
- `app/Utils/Casts/Attributes/CastedValue.php`
