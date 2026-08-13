# Config Blocks

How typed configuration reaches the frontend on bootstrap. Config blocks are the prototype for the v3 database-backed configuration system — `AbstractConfig` currently uses a `make()` method to polyfill the non-existing database read part, but the design is intended to evolve into DB-backed config once the admin panel and plugin system land.

## When to add a config block

Add a public config block when the frontend needs a stable, typed set of values at boot time — crypto salts, locale defaults, AI handle, file upload limits, passkey UX settings. The frontend fetches all registered blocks from the `configs` JSON:API resource on startup (see [Connection Bootstrap](../300-HTTP-API/200-Connection-Bootstrap.md)).

## How to build one

A config block is an `AbstractConfig` subclass — itself a subclass of `AbstractCastableObject` (see [Model Casts](./160-Model-Casts/index.md)) — with `public` typed properties:

```php
use App\Config\AbstractConfig;

class LocaleConfig extends AbstractConfig
{
    public string $default = 'en';
    public array $available = ['en', 'de'];
}
```

Each config class declares:

- `publicKey(): string` — the key the frontend reads under `configs.{key}`.
- `toPublicArray(Request): array` — the payload sent to the frontend.

## How to register it

Register the class with `PublicConfigRegistry` in a `ServiceProvider::boot()`:

```php
$this->app->extend(PublicConfigRegistry::class, function (PublicConfigRegistry $registry) {
    return $registry->declare(MyConfigBlock::class);
});
```

Built-in blocks live under the `hawki-core` namespace: `locale`, `salts`, `security`, `transfer`, `ai`, `storage_files`. See [Connection Bootstrap](../300-HTTP-API/200-Connection-Bootstrap.md) for which blocks reach which audience (guest, authenticated user, external app).

## Dragons

- **Salts are only delivered to authenticated users.** `SaltConfig` returns an empty payload for guests. Do not move salt delivery into a guest-visible block.
- **Do not put secrets in a public config block.** If a value must not reach the browser, it does not belong here. The DB-backed config layer (planned, see [Roadmap](../700-Roadmap/100-Plugin-System.md)) is the home for server-only sensitive config.
- **Do not duplicate Laravel's config-file pattern.** Config blocks are meant to evolve into DB-backed configuration, not to mirror `config/*.php` files. The `AbstractConfig` base currently polyfills the database read via a `make()` method — this is transitional and will be replaced.
