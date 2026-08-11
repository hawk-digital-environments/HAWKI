# Writing a Plugin

A **plugin** is HAWKI's unit of feature composition. Instead of every feature reaching into the app directly, a plugin implements the lifecycle hooks it needs — `stores`, `resourceSchemas`, `modules`, `routes`, `boot`, … — and the kernel calls them at the right point in startup. The single first-party plugin is `core` (`resources/js/plugins/core/core.plugin.ts`); it registers the core stores, snippets, and migrations.

Source: `resources/js/kernel/plugins/types.ts` (contract), `kernel/plugins/PluginExtension.ts` (discovery + dispatch).

:::info[Third-party plugins: not yet]
Only **built-in** plugins are supported today — they are auto-discovered from `$lib/plugins/**/*.plugin.ts` at build time via `import.meta.glob`, so a plugin must be bundled with the app. Loading third-party (runtime-installed) plugins is planned for **HAWKI v3.0.0**; `PluginExtension.autoRegisterInstalledPlugins()` is currently a no-op placeholder. Until then, every plugin is a core plugin.
:::

---

## The contract

```ts
export interface HawkiPlugin {
    readonly name: string;

    init?(context: HawkiPluginContext): void | Promise<void>;

    extensions?(registrar: AppExtensionRegistrar, context: HawkiPluginContext): void | Promise<void>;

    resourceSchemas?(registrar: ResourceSchemaRegistrar, context: HawkiPluginContext): void | Promise<void>;

    configSchemas?(registrar: ConfigSchemaRegistrar, context: HawkiPluginContext): void | Promise<void>;

    modules?(registrar: ModuleRegistrar, context: HawkiPluginContextWithConfig): void | Promise<void>;

    routes?(registrar: RouteRegistrar, context: HawkiPluginContextWithConfig): void | Promise<void>;

    stores?(registrar: StoreRegistrar, context: HawkiPluginContextWithConfig): void | Promise<void>;

    boot?(app: HawkiApp, context: HawkiPluginContextWithConfig): void | Promise<void>;

    ready?(app: HawkiApp, context: HawkiPluginContextWithConfig): void | Promise<void>;
}

/** Core (built-in) plugins additionally may register migrations — third-party plugins cannot. */
export interface HawkiCorePlugin extends HawkiPlugin {
    migrations?(registrar: MigrationRegistrar, context: HawkiPluginContextWithConfig): void | Promise<void>;
}
```

Every hook is optional — implement only what you need. Two context shapes are passed in:

- `HawkiPluginContext` = `{ client, bootstrapper, plugins }` — for the early hooks (`init`, `extensions`, `resourceSchemas`, `configSchemas`) that run before config is parsed.
- `HawkiPluginContextWithConfig` = that plus `config` — for the later hooks (`modules`, `routes`, `stores`, `migrations`, `boot`, `ready`).

---

## When each hook runs

`PluginBootstrapper` dispatches each hook to every plugin in registration order, isolating failures so one broken plugin can't block the others. Each hook is called by whichever extension owns that concern, at the point in startup where it makes sense:

| Hook              | Called by                       | When                                                                                            |
|-------------------|---------------------------------|-------------------------------------------------------------------------------------------------|
| `init`            | `PluginExtension.init()`        | first, before app extensions or schemas exist                                                   |
| `extensions`      | `PluginExtension.init()`        | right after `init` — register further `HawkiAppExtension`s via `registrar.addExtension()`       |
| `resourceSchemas` | `PluginExtension.init()`        | during assembly — register Zod resource schemas                                                 |
| `configSchemas`   | `ConfigurationExtension.init()` | during assembly — register Zod config schemas                                                   |
| `modules`         | `ModuleExtension.init()`        | during assembly — register feature modules                                                      |
| `stores`          | `StoreExtension.init()`         | during assembly — register data stores                                                          |
| `routes`          | routing extension               | (routing not yet wired — see [Routing](200-Routing.md))                                         |
| `migrations`      | `MigrationExtension`            | core plugins only                                                                               |
| `boot`            | `PluginExtension.ready()`       | after the `preparation` stage passes (config + connection available; stores **not** yet loaded) |
| `ready`           | `PluginExtension.ready()`       | at the `finalization` stage, just before the Svelte app mounts                                  |

Stores registered in `stores()` have their `loadData(app)` called automatically on the `main` stage by `StoreExtension` — you don't trigger that yourself.

---

## Making `app.plugins.get('myPlugin')` typed

Augment `HawkiPlugins` next to your plugin class:

```ts
declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiPlugins {
        myPlugin: MyPlugin;
    }
}
```

Now `app.plugins.get('myPlugin')` returns a typed `MyPlugin` instead of the generic `HawkiPluginWithMetadata`. The same declaration-merging pattern applies to `HawkiResourceSchemas`/`HawkiConfigSchemas` (next to each schema file) and `HawkiDataStores` (next to each store class) — see [The App & Kernel](110-App-and-Kernel.md).

---

## The reference implementation: `CorePlugin`

The only first-party plugin is `resources/js/plugins/core/core.plugin.ts`. It implements `name`, `boot`, `migrations`, and `stores`. Open the file for the full shape; the extracts below show how each concern is registered and what is non-obvious about the timing.

### Registering stores

`stores()` receives a `StoreRegistrar` whose `add()` takes a `DataStore` instance. The plugin just hands class instances over — `StoreExtension` owns the `loadData(app)` scheduling on the `main` stage, so the plugin does not trigger hydration itself.

```ts
class Plugin implements HawkiCorePlugin {
    public stores({add}: StoreRegistrar): void | Promise<void> {
        add(new KeychainStore());
        add(new AiModelStore());
        // …
    }
}
```

### Registering snippets

`boot()` runs after the `preparation` stage, so `app` and `ctx.config` are available but stores are not yet loaded. That is why the core plugin registers Svelte snippets here (no data dependency) rather than hydrating stores. Each `.svelte` file under the plugin's `snippets/` directory is eager-globbed and handed to `app.snippets.register(name, Component)`:

```ts
class Plugin implements HawkiCorePlugin {
    public boot(app: HawkiApp, ctx: HawkiPluginContextWithConfig): void | Promise<void> {
        const glob = import.meta.glob('$lib/plugins/core/snippets/**/*.svelte', {eager: true});
        for (const [path, module] of Object.entries(glob)) {
            const snippetName = path.split('/').pop()?.replace('.svelte', '');
            if (snippetName) {
                app.snippets.register(snippetName, (module as { default: Component }).default);
            }
        }
        // …
    }
}
```

:::caution[Deprecated]
The whole concept of snippets is transitional — the SPA rewrite will give the page a single Svelte root, so snippets will no longer be needed. See [Old UI Integration](300-Old-Ui.md).
:::

### Registering migrations

`migrations` is a `HawkiCorePlugin` hook — third-party plugins implement `HawkiPlugin`, which has no `migrations`; the kernel skips it for them. The registrar infers the run type from the directory the file lives in, so the plugin only hands over a lazy glob:

```ts
class Plugin implements HawkiCorePlugin {
    public migrations(registrar: MigrationRegistrar): void | Promise<void> {
        registrar.addFromModules(import.meta.glob('$lib/plugins/core/migrations/**/*.ts', {eager: false}));
    }
}
```

---

## A minimal new plugin

```ts
// resources/js/plugins/myPlugin/myPlugin.plugin.ts
import type {HawkiPlugin, HawkiPluginContextWithConfig} from '$lib/kernel/plugins/types.js';
import type {StoreRegistrar} from '$lib/kernel/stores/storeRegistrar.js';
import {MyStore} from './stores/MyStore.svelte.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiPlugins {
        myPlugin: MyPlugin;
    }
}

export default class MyPlugin implements HawkiPlugin {
    readonly name = 'myPlugin';

    public stores({add}: StoreRegistrar): void | Promise<void> {
        add(new MyStore());
    }

    public ready(app: HawkiApp, ctx: HawkiPluginContextWithConfig): void | Promise<void> {
        // Runs at the finalization stage, just before the Svelte app mounts.
    }
}
```

That's the whole wiring — the file's location (`$lib/plugins/myPlugin/myPlugin.plugin.ts`) and the `default` export are what make the kernel discover it. No registration call anywhere.

---

## Plugin discovery rules

- The file must match `$lib/plugins/**/*.plugin.ts` and `export default` a class implementing `HawkiPlugin` (or `HawkiCorePlugin`).
- The class must have a non-empty string `name`. Duplicate names are skipped with a console warning.
- Discovery is eager (`import.meta.glob(…, {eager: true})`) — the plugin is bundled, not loaded at runtime.
- A hook that throws is caught and logged (`Error while running plugin <name>: …`); the kernel continues with the next plugin. Don't rely on that — write hooks that don't throw.

---

## Where to go next

| I want to…                                                | Read                                                |
|-----------------------------------------------------------|-----------------------------------------------------|
| Understand the extension system plugins plug into         | [The App & Kernel](110-App-and-Kernel.md)           |
| Add a fundamentally new app-wide subsystem (not a plugin) | [Writing an Extension](120-Writing-an-Extension.md) |
| See how registered stores are consumed                    | [Stores](../300-Data/100-Stores.md)                 |
| See the full plugin contract                              | `resources/js/kernel/plugins/types.ts`              |
| See the dispatch order                                    | `resources/js/kernel/plugins/PluginBootstrapper.ts` |
