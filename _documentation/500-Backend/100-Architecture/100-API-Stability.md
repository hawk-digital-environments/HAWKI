# API Stability & Extension

This article covers the `@api` contract, the decorator pattern for wrapping services, and all currently implemented extension hooks. Plugin authors and anyone adding code that other code will depend on should read this first.

## The `@api` contract

A class or method marked with `@api` in its DocBlock is part of the **stable public surface**:

- The signature will not change before the next major version.
- Removal requires a `@deprecated` tag first, with the target version and migration path.

Everything **without** `@api` is internal. It may change at any time and must not be depended on from outside its domain.

```php
/**
 * @api
 */
class AiService
{
    /**
     * @api
     * @deprecated Will be removed in v3.0. Use getAvailableModels() instead.
     */
    public function getModels(): AiModelCollection { ... }
}
```

If a class carries `@api` but none of its methods do, the entire public and protected surface is considered stable.

**Events marked `@api`** must use `public readonly` properties and past-tense names.

**Eloquent models marked `@api`** declare their stable surface via class-level `@property` docblocks and public relationship methods. Private implementation details remain internal even on `@api` classes.

## `@api` classes are never `final`

`@api` classes and methods are intentionally not `final`. HAWKI is designed with a plugin system in mind — `@api` classes must remain open for decoration and extension via `$app->extend()`.

Internal (non-`@api`) classes may be `final` with no stability guarantee.

## Decorating `@api` services

To wrap an `@api` service without modifying core code, use `App\Utils\DecoratorTrait` together with Laravel's `$app->extend()`. The trait uses reflection to copy all constructor-injected properties from the original instance into your decorator, so you only need to override the methods you care about.

```php
// DecoratedAiService.php
class DecoratedAiService extends AiService
{
    use DecoratorTrait;

    public function getModels(): AiModelCollection
    {
        $models = parent::getModels();
        return $this->filterByPolicy($models);
    }
}

// In a ServiceProvider
$this->app->extend(AiService::class, function (AiService $original) {
    return DecoratedAiService::createDecoratedOf($original);
});
```

`DecoratorTrait` is **the designated mechanism for plugin authors** to customise service behaviour. Any `$app->extend()` in a plugin should use it.

:::caution[Reflection caveat]
`DecoratorTrait` copies public and protected properties. It cannot copy private properties, so decorators that rely on private internals are not safe. Call only `public` and `protected` methods from your decorator.
:::

## Current extension points

These extension points are fully implemented today. None require a v3 plugin system.

| Extension point                      | How to register                                                                          | Stability               |
|--------------------------------------|------------------------------------------------------------------------------------------|-------------------------|
| `ProviderAdapterRegistry::declare()` | `$r->declare('my_key', MyAdapter::class)` in `ServiceProvider::boot()`                   | `@api`                  |
| `AgentRegistry::declare()`           | `$r->declare(MyFactory::class, before: ..., after: ...)` in `ServiceProvider::boot()`    | Stable                  |
| `AiModelSettingRegistry`             | `$app->extend(AiModelSettingRegistry::class, fn($r) => $r->register('my_setting', ...))` | Stable                  |
| `AiModelCapabilityRegistry`          | `$app->extend(AiModelCapabilityRegistry::class, ...)`                                    | Stable                  |
| `PublicConfigRegistry`               | `$app->extend(PublicConfigRegistry::class, fn($r) => $r->register(...))`                 | Stable                  |
| Container tag `'ai.tool'`            | `$app->tag([MyTool::class], 'ai.tool')` in `ServiceProvider::register()`                 | Stable                  |
| `HealthCheckEvent::addResult()`      | Add a listener to `HealthCheckEvent`                                                     | Stable                  |
| `DecoratorTrait` + `$app->extend()`  | Wrap any `@api` service                                                                  | `@api`                  |
| Filter events (`DispatchableFilter`) | Add a listener to any `...FilterEvent` class                                             | `@api` varies per event |
| Event auto-discovery                 | Place listeners in `app/Services/*/Listeners/`                                           | Stable                  |

### `ProviderAdapterRegistry::declare()`

Registers a new AI provider adapter. The adapter must implement `ProviderAdapterInterface`. Call `declare()` in `ServiceProvider::boot()` — no core code change required.

```php
$this->app->extend(ProviderAdapterRegistry::class, function (ProviderAdapterRegistry $registry) {
    return $registry->declare('my_provider', MyProviderAdapter::class);
});
```

See [Provider Adapters](../500-AI-Service-Layer/100-Provider-Adapters.md) for the full interface contract.

### `AgentRegistry::declare()`

Registers a custom agent factory. The `before:` and `after:` parameters control which factory claims the request first. Earlier factories win.

```php
$this->app->extend(AgentRegistry::class, function (AgentRegistry $registry) {
    return $registry->declare(MyAgentFactory::class, before: ChatAgentFromLegacyRequestFactory::class);
});
```

### `AiModelSettingRegistry` and `AiModelCapabilityRegistry`

Add per-model runtime toggles or capability declarations with UI metadata:

```php
$this->app->extend(AiModelSettingRegistry::class, function (AiModelSettingRegistry $registry) {
    return $registry->register('my_setting', defaultValue: false, description: '...');
});
```

### `PublicConfigRegistry`

Add a config block to the `configs` JSON:API resource. The frontend reads all registered blocks on startup:

```php
$this->app->extend(PublicConfigRegistry::class, function (PublicConfigRegistry $registry) {
    return $registry->register(MyConfigBlock::class);
});
```

### `HealthCheckEvent::addResult()`

Any listener registered via auto-discovery can inject a custom health check:

```php
class CheckMyServiceHealth
{
    public function handle(HealthCheckEvent $event): void
    {
        $ok = $this->ping();
        $event->addResult('my_service', $ok, $ok ? null : 'Connection failed');
    }
}
```

### Filter events

Filter events (`DispatchableFilter`) are the synchronous hook mechanism for modifying data in pipelines. Add a listener to any `...FilterEvent` class to intercept the pipeline at that point:

```php
class MyModelPermissionListener
{
    public function handle(ModelPermissionFilterEvent $event): void
    {
        if ($this->policy->denies($event->getUser(), $event->getModel())) {
            $event->setAllowed(false);
        }
    }
}
```

## The v3 plugin system

The extension points above establish the stable surface that the HAWKI v3 plugin system will build on. The plugin system itself (`HawkiPluginInterface`, `PluginRegistry`, Composer lifecycle hooks) is not yet implemented.

See [Plugin System Preview](../1000-Infrastructure/100-Plugin-System-Preview.md) for the full design.
