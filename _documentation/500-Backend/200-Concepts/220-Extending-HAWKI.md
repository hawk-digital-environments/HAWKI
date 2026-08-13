# Extending HAWKI

The live extension points you can use today, without waiting for the v3 plugin system. Plugin-author audience; the not-yet-implemented plugin lifecycle lives in the [Roadmap](../700-Roadmap/100-Plugin-System.md).

## Extension points at a glance

| Extension point                      | How to register                                                                          | Stability               |
|--------------------------------------|------------------------------------------------------------------------------------------|-------------------------|
| `ProviderAdapterRegistry::declare()` | `$r->declare('my_key', MyAdapter::class)` in `ServiceProvider::boot()`                   | `@api`                  |
| `AgentRegistry::declare()`           | `$r->declare(MyFactory::class, before: ..., after: ...)` in `ServiceProvider::boot()`    | Stable                  |
| `AiModelSettingRegistry`             | `$app->extend(AiModelSettingRegistry::class, fn($r) => $r->declare(key:, defaultValue:, ...))` | `@api`                  |
| `AiModelCapabilityRegistry`          | `$app->extend(AiModelCapabilityRegistry::class, fn($r) => $r->declare(key:, ..., iconPath:))` | Stable                  |
| `PublicConfigRegistry`               | `$app->extend(PublicConfigRegistry::class, fn($r) => $r->register(...))`                 | Stable                  |
| Container tag `'ai.tool'`            | `$app->tag([MyTool::class], 'ai.tool')` in `ServiceProvider::register()`                 | Stable                  |
| `HealthCheckEvent::addResult()`      | Add a listener to `HealthCheckEvent`                                                     | Stable                  |
| `DecoratorTrait` + `$app->extend()`  | Wrap any `@api` service                                                                  | `@api`                  |
| Filter events (`DispatchableFilter`) | Add a listener to any `...FilterEvent` class                                             | `@api` varies per event |
| Event auto-discovery                 | Place listeners in `app/Services/*/Listeners/`                                           | Stable                  |
| `FileConverterInterface`             | Register a converter class in `config/files.php` under `converters`                      | Stable                  |

## Register a new AI provider adapter

`ProviderAdapterRegistry::declare()` registers a new AI provider adapter. The adapter must implement `ProviderAdapterInterface`. Call `declare()` in `ServiceProvider::boot()` — no core code change required.

```php
$this->app->extend(ProviderAdapterRegistry::class, function (ProviderAdapterRegistry $registry) {
    return $registry->declare('my_provider', MyProviderAdapter::class);
});
```

See [Providers & Adapters](../400-Domains/100-AI/110-Providers-and-Adapters.md) for the full interface contract.

## Register a custom agent factory

`AgentRegistry::declare()` registers a custom agent factory. The `before:` and `after:` parameters control which factory claims the request first. Earlier factories win.

```php
$this->app->extend(AgentRegistry::class, function (AgentRegistry $registry) {
    return $registry->declare(MyAgentFactory::class, before: ChatAgentFromLegacyRequestFactory::class);
});
```

A factory returns `null` to decline a request, allowing higher-priority factories to claim it. This lets a plugin insert a custom agent type that takes priority over the built-in chat flow. See [AI Service Layer](../400-Domains/100-AI/index.md).

## Add a per-model setting or capability

`AiModelSettingRegistry` adds a runtime toggle per model; `AiModelCapabilityRegistry` adds a capability declaration with UI metadata. Both use `declare()`:

```php
// Per-model setting
$this->app->extend(AiModelSettingRegistry::class, function (AiModelSettingRegistry $registry) {
    return $registry->declare(
        key: 'my_plugin.feature_x',
        defaultValue: false,
        titleTranslationLabel: 'settings.feature_x.title',
        descriptionTranslationLabel: 'settings.feature_x.description',
    );
});

// Capability with UI metadata
$this->app->extend(AiModelCapabilityRegistry::class, function (AiModelCapabilityRegistry $registry) {
    return $registry->declare(
        key: WellKnownCapabilities::WEB_SEARCH,
        titleTranslationLabel: 'capabilities.web_search.title',
        descriptionTranslationLabel: 'capabilities.web_search.description',
        iconPath: resource_path('icons/web-search.svg'),
    );
});
```

:::caution[`iconPath` must be an absolute filesystem path]
Pass `resource_path('icons/...')` or an absolute path, not a public URL. The API layer converts the file to a base64 data URI before sending it to clients.
:::

See [Models & Registries](../400-Domains/100-AI/120-Models-and-Registries.md).

## Add a public config block

See [Config Blocks](./200-Config-Blocks.md). `$app->extend(PublicConfigRegistry::class, ...)` adds a custom config block to the `configs` JSON:API resource without touching core code.

## Register an AI tool

Tag the tool class with `'ai.tool'` in `ServiceProvider::register()`:

```php
$this->app->tag([MyTool::class], 'ai.tool');
```

The tool must implement `ToolInterface`. See [AI Tools](../400-Domains/100-AI/130-Tools.md).

## Add a health check

Any listener auto-discovered from `app/Services/*/Listeners/` can inject a custom health check:

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

See [Health Checks](../600-Infrastructure/100-Health-Checks.md).

## Decorate an `@api` service

Use `DecoratorTrait` + `$app->extend()` to wrap any `@api` service. See [API Stability](./210-API-Stability.md) for the pattern.

## Intercept a pipeline via a filter event

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

See [Events & Listeners](./170-Events-and-Listeners.md) for the filter-event contract.

## Register a file converter

Register a converter class implementing `FileConverterInterface` in `config/files.php` under `converters`. See [File Converter](../400-Domains/300-Storage/320-File-Converter.md).

## The v3 plugin system

The extension points above establish the stable surface that the HAWKI v3 plugin system will build on. The plugin system itself (`HawkiPluginInterface`, `PluginRegistry`, Composer lifecycle hooks, DB-backed config, SyncLog, frontend slot/zone) is not yet implemented. See [Roadmap — Plugin System](../700-Roadmap/100-Plugin-System.md).
