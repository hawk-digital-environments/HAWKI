# Plugin System Preview

This article is the single home for all content about HAWKI's plugin extension system — both
what works **today** (no v3 required) and what is planned for HAWKI v3. Individual domain
articles say "this is a plugin extension point → see Plugin System Preview" rather than
repeating this content inline.

---

## Currently Implemented Extension Points

The following hooks are fully operational in HAWKI today. You can use them from a Laravel
service provider in any package or overlay without modifying HAWKI core code.

| Extension point | How to use | Stability |
|---|---|---|
| `ProviderAdapterRegistry::declare()` | `$r->declare('my_key', MyAdapter::class)` in `ServiceProvider::boot()` | `@api` |
| Container tag `'ai.tool'` | `$app->tag([MyTool::class], 'ai.tool')` in `ServiceProvider::register()` | Stable |
| `AgentRegistry::declare(before/after)` | `$r->declare(MyFactory::class, before: 'chat')` in `ServiceProvider::boot()` | Stable |
| `AiModelSettingRegistry` | `$app->extend(AiModelSettingRegistry::class, fn($r) => $r->register(...))` | Stable |
| `AiModelCapabilityRegistry` | `$app->extend(AiModelCapabilityRegistry::class, fn($r) => $r->register(...))` | Stable |
| `PublicConfigRegistry` | `$app->extend(PublicConfigRegistry::class, fn($r) => $r->add(...))` | Stable |
| `HealthCheckEvent::addResult()` | Add a listener to `HealthCheckEvent` in any auto-discovered `Listeners/` directory | Stable |
| `DecoratorTrait` + `$app->extend()` | Wrap any `@api`-marked service class | `@api` |
| Filter events (`DispatchableFilter`) | Add a listener to any filter event class | `@api` varies per event |
| Event auto-discovery | Place listeners in `app/Services/*/Listeners/` (or any registered discovery path) | Stable |

### Notes on specific points

**`ProviderAdapterRegistry::declare()`** — this is the primary hook for adding a new AI
provider adapter. Implement `ProviderAdapterInterface` (8 methods), then declare it:
```php
// In your ServiceProvider::boot():
$this->app->extend(ProviderAdapterRegistry::class, function (ProviderAdapterRegistry $registry) {
    return $registry->declare('my_provider', MyProviderAdapter::class);
});
```
No core code changes required. See [Provider Adapters](../500-AI-Service-Layer/100-Provider-Adapters.md)
for the full adapter contract.

**Container tag `'ai.tool'`** — the `FunctionToolSyncer` discovers tools via container tag.
Register a custom function tool:
```php
// In your ServiceProvider::register():
$this->app->tag([MyCustomTool::class], 'ai.tool');
```
`ai:tools:sync` will pick it up on the next run.

**Filter events** — `DispatchableFilter` is the synchronous hook pattern for modifying data in
pipelines. Add a listener to any filter event (e.g. `BeforeCallingMcpToolFilterEvent`,
`ModelPermissionFilterEvent`, `ResolvingWebsiteMetadataFilterEvent`) to intercept and modify
data without subclassing core services. Six `ExternalContent` filter events and several AI tool
filter events are the first examples at plugin scale.

---

## [v3 Feature] — The Full Plugin System

The items below describe planned functionality for HAWKI 3.0. None of this requires any action
today. It is documented here so that current architectural choices — `@api` markers, registry
patterns, filter events, `DecoratorTrait` — make sense as load-bearing preparations.

### Plugin Entry Point

**`HawkiPluginInterface` / `AbstractHawkiPlugin`** — every plugin implements
`HawkiPluginInterface`. The `AbstractHawkiPlugin` base class provides default implementations
for optional lifecycle methods. Plugins declare their identity, dependencies (for ordering), and
the service providers they contribute.

### Plugin Discovery and Loading

**`PluginRegistry`** — discovers installed plugins from Composer package metadata, topologically
sorts them via `IntuitiveTopSorter` (respecting declared `before:` / `after:` dependencies),
and loads them in order. Uses `LazySingletonList` internally, the same pattern already used by
`ProviderAdapterRegistry` and `AgentRegistry`.

**`InstalledPlugins`** — the runtime list of active plugin instances, queryable at boot time.

### Plugin Asset and Config Publishing

**`PluginPublisher`** — copies plugin assets, config files, and migration files into the host
application on install. Works similarly to `vendor:publish` but plugin-aware.

### Plugin Route and Event Registration

**`PluginRouteBuilder`** — registers each plugin's routes, adds its `Listeners/` directory to
the event auto-discovery paths (reusing the same `app/Services/*/Listeners` glob already in
`bootstrap/app.php`), and wires any custom middleware.

### Composer Lifecycle Hooks

The following commands will be wired as Composer `post-update-cmd` / `pre-uninstall-cmd` hooks
so that plugin installation and removal are handled automatically:

- `hawki:plugins:composer:post-update` — runs `PluginPublisher` after `composer update`
- `hawki:plugins:composer:uninstall` — cleans up plugin assets and config on removal

These are internal hidden commands; operators do not call them directly.

### Operator Commands

- `hawki:plugin:list` — lists all installed plugins with name, version, and status
- `make:hawki:plugin` — interactive scaffolding wizard that generates a plugin skeleton with
  correct `composer.json` metadata, a service provider, a boilerplate `AgentFactory`, and a
  tool registration example

### DB-Backed Configuration

Currently, `AbstractConfig` classes are file-backed (read from config PHP files). In v3,
plugins contribute configuration through a DB-backed system:

- `config_values` table — stores per-plugin config values with schema validation
- `ConfigSchema` / `ConfigBlueprint` — typed schema definitions for plugin config blocks
- `AbstractConfig` gains a DB namespace and `PluginAwareTrait` so that plugin-contributed
  config is isolated from core config

### SyncLog System Completion

The SyncLog system is designed but currently disabled. In v3 it will be fully enabled,
giving the frontend a reliable incremental change feed.

**Architecture:**

- `sync_logs` table — append-only log of entity changes
- `SyncLogTracker` — writes entries as domain events fire
- Per-entity handlers (Room, Member, User, UserKeychainValue, etc.) — registered via the
  `syncLog.handler` container tag. Plugins add custom entity handlers:
  ```php
  $this->app->tag([MyEntitySyncHandler::class], 'syncLog.handler');
  ```
- `SyncLogEntryType` — 10 entry types (room, member, user, room_invitation, keychain_value, etc.)
- `SyncLogEntryAction` — `SET` (create or update) or `REMOVE` (delete)
- **Audience model**: `null` audience = global broadcast to `PrivateChannel('AllUsers')`; a
  user collection = targeted broadcast to `PrivateChannel('User.{id}')` for each user
- `SyncLogEvent` — broadcasts over Reverb
- `IncrementalSyncLogGenerator` — sends only changes since the client's last known position
- `FullSyncLogGenerator` — sends the complete current state as a fallback when the client's
  position is too old or unknown

The `_hawki_sync_log` meta slot is already present in all mutating JSON:API responses. Today
the slot is empty. When the SyncLog system is enabled in v3, this slot will carry the log
entry for each mutation.

### Frontend Slot/Zone Model

Plugins will be able to contribute UI components that are mounted in designated zones of the
HAWKI interface (for example, a sidebar panel or a toolbar action). The exact zone API design
is not yet settled.

---

## Architectural Context

Several current patterns feel heavier than their immediate use warrants. They are groundwork for
the v3 plugin system, not over-engineering:

| Pattern | Current use | v3 purpose |
|---|---|---|
| `@api` marker | Internal stability contract | Governs plugin-to-core API surface without any surface change |
| `LazySingletonList` | `ProviderAdapterRegistry`, `AgentRegistry` | Shared pattern for all plugin-aware registries |
| `IntuitiveTopSorter` | `AgentRegistry` ordering | Plugin load-order resolution |
| Filter events | ExternalContent, AI tools | Primary data-interception hook for plugins |
| `DecoratorTrait` | Manual service wrapping | Designated mechanism for plugins to extend `@api` services |
| Event auto-discovery | `app/Services/*/Listeners` | Plugin listener directories added to the same glob |
