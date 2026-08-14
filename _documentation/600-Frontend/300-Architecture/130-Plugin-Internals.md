# Plugin Internals

How plugins are discovered and how the kernel dispatches their lifecycle hooks. For authoring a plugin (the contract, examples, declaration merging), see [Extending HAWKI](../../700-Extending-Hawki/index.md) — this page covers the internals a contributor needs to understand what they're plugging into.

Source: `kernel/plugins/PluginExtension.ts` (discovery), `kernel/plugins/PluginBootstrapper.ts` (dispatch), `kernel/plugins/types.ts` (contract).

## Discovery

`PluginExtension.init()` auto-discovers every plugin via `import.meta.glob('$lib/plugins/**/*.plugin.ts', {eager: true})`. Each file must `export default` a class implementing `HawkiPlugin` (or `HawkiCorePlugin`) with a non-empty `name`. Duplicate names are skipped with a console warning.

Discovery is eager — the plugin is bundled, not loaded at runtime. Third-party (runtime-installed) plugins are planned for a future version; `autoRegisterInstalledPlugins()` is currently a no-op placeholder. Until then, every plugin is a core plugin (bundled with the app).

The discovered plugins are wrapped with metadata (`HawkiPluginWithMetadata`, carrying `isCorePlugin: true` today) and handed to a new `PluginBootstrapper` exposed as `app.plugins.bootstrapper`.

## Dispatch

`PluginBootstrapper.runForEach` runs a callback against every plugin in registration order, catching and logging any error so a single failing plugin doesn't block the rest (`Error while running plugin <name>: …`). Don't rely on that — write hooks that don't throw.

Each `run*` method is invoked by whichever extension owns that concern, at the point in startup where it makes sense — they are not called all at once:

| Hook | `run*` method | Called by | When |
|---|---|---|---|
| `init` | `runInit` | `PluginExtension.init()` | first, before app extensions or schemas exist |
| `extensions` | `runExtensions` | `PluginExtension.init()` | right after `init` — register further `HawkiAppExtension`s |
| `resourceSchemas` | `runResourceSchemas` | `PluginExtension.init()` | during assembly |
| `configSchemas` | `runConfigSchemas` | `ConfigurationExtension.init()` | during assembly |
| `modules` | `runModules` | `ModuleExtension.init()` | during assembly |
| `stores` | `runStores` | `StoreExtension.init()` | during assembly |
| `routes` | `runRoutes` | `RoutingExtension.init()` | during assembly, wrapped in a `registrar.group()` under the plugin's route prefix |
| `migrations` | `runMigrations` | `MigrationExtension` | core plugins only — skipped for third-party |
| `boot` | `runBoot` | `PluginExtension.ready()` → `onStagePassed('preparation')` | after `preparation` (config + connection available; stores **not** yet loaded) |
| `ready` | `runReady` | `PluginExtension.ready()` → `onStageReached('finalization')` | at `finalization`, just before the Svelte app mounts |

Stores registered in `stores()` have their `loadData(app)` called automatically on the `main` stage by `StoreExtension` — the plugin does not trigger that itself.

## Context shapes

Two context shapes are passed to plugin hooks:

- `HawkiPluginContext` = `{ client, bootstrapper, plugins }` — for the early hooks (`init`, `extensions`, `resourceSchemas`, `configSchemas`) that run before config is parsed.
- `HawkiPluginContextWithConfig` = that plus `config` — for the later hooks (`modules`, `routes`, `stores`, `migrations`, `boot`, `ready`).

`PluginBootstrapper.setConfig()` (called by `ConfigurationExtension` once config is parsed) extends the base context with `config`. Any `run*` method that needs `contextWithConfig` throws if `setConfig` hasn't been called yet.

## Route prefixing

`runRoutes` wraps each plugin's `routes()` in a `registrar.group(...)` carrying the plugin's route prefix (see `getPluginRoutePrefix` in `kernel/routing/routeInflection.ts`): empty for core plugins, `/plugins/<slug>` for third-party. `createModuleRegistrar` does the same for module routes, so module routes are namespaced under the plugin's prefix automatically.

## Where to go next

| I want to… | Read |
|---|---|
| Author a plugin or extension | [Extending HAWKI](../../700-Extending-Hawki/index.md) |
| Understand the boot stages hooks dispatch against | [App Startup](110-App-Startup.md) |
| Understand modules and routing | [Modules & Routing](120-Modules-and-Routing.md) |
| See the full plugin contract | `resources/js/kernel/plugins/types.ts` |
