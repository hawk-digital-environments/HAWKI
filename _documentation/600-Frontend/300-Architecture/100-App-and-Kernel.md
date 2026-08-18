# The App & Kernel

The kernel is the small, extension-assembled core of the frontend. There is no god object: `HawkiApp` is an empty shell that is built up at startup from a list of extensions, each contributing one subsystem (config, client, stores, plugins, routing, shell, …). Everything else in the app reaches those subsystems through the resulting `app` instance or the hooks that wrap it.

Source: `resources/js/kernel/`.

---

## How the app is assembled

`app.ts` creates a `Bootstrapper` and hands it plus an ordered list of extensions to `createApp()`. Each extension's `init()` runs in array order, then its `provideProperties()` are merged onto the app object as real properties. Once every extension is added, `ready()` runs on each (same order). The `Bootstrapper` is then started to run the six boot stages.

```ts
// resources/js/app.ts
const bootstrapper = new Bootstrapper();

setHawkiApp(await createApp(
    bootstrapper,
    [
        new ResourceSchemaExtension(),   // 1. Zod schema registry (no dependencies)
        new ClientExtension(),           // 2. HTTP client + connection (needs resourceSchemas)
        new PluginExtension(),           // 3. discovers & drives all plugins (needs client)
        new ConfigurationExtension(),    // 4. server config (needs plugins to register config schemas)
        new MigrationExtension(),        // 5. frontend migrations
        new LocalizationExtension(),     // 6. locale + translator (needs connection + config)
        new ModuleExtension(),           // 7. feature-module registry (needs plugins)
        new RoutingExtension(),          // 8. route registry + router build (needs plugins + modules)
        new StoreExtension(),            // 9. data-store registry (needs plugins)
        new ShellExtension(),            // 10. SPA shell mount + legacy snippet fallback
        new SnippetExtension(),          // 11. legacy snippet registry (@deprecated, transitional)
        new LegacyToastExtension()       // 12. app-wide toast holder (@deprecated, transitional)
    ]
));

await bootstrapper.run();
```

:::tip[Order matters]
`init()` runs in array order, so an extension may only reach extensions registered *before* it (via `app.getOrFail('name')`). `PluginExtension` can register further extensions from within its `init()` — those are queued and processed before `ready()` runs on anyone. Reorder only when you understand the dependency chain.
:::

---

## The `app.*` surface

Every property below is contributed by an extension's `provideProperties()` and typed through declaration merging (see next section). Components should reach these through the hooks in `app/hooks/` rather than grabbing `app` directly when a dedicated hook exists.

| Property | Role | Provided by |
|---|---|---|
| `app.config` | Namespaced, Zod-validated server config (`app.config.get('hawki-core')`) | `ConfigurationExtension` |
| `app.client` | HTTP client bundle (restApi, connection) | `ClientExtension` |
| `app.restApi` | Typed JSON:API client (`getResource`, `getResourceCollection`, …) | `ClientExtension` |
| `app.uriBuilder` | Builds API/asset/link-preview URIs | `ClientExtension` |
| `app.connection` | Current connection (discriminated union on `type`) | `ClientExtension` |
| `app.authenticatedConnection` | Connection narrowed to authenticated (throws otherwise) | `ClientExtension` |
| `app.connectionWithUserInfo` | Connection narrowed to authenticated or registering | `ClientExtension` |
| `app.linkPreviewApi` | Link-preview fetching | `ClientExtension` |
| `app.resourceSchemas` | Registry of Zod schemas for JSON:API resources | `ResourceSchemaExtension` |
| `app.plugins` | Plugin registry + plugin lifecycle driver | `PluginExtension` |
| `app.migration` | Frontend migration runner | `MigrationExtension` |
| `app.localization` | Active locale + loaded label sets | `LocalizationExtension` |
| `app.translator` | Ready `Translator` (`__`, `translate`, …) | `LocalizationExtension` |
| `app.modules` | Feature-module registry (`core:chat`, …) | `ModuleExtension` |
| `app.router` | Compiled router handle (`router.resolve(pathname)`) | `RoutingExtension` |
| `app.stores` | Data-store registry (`app.stores.get('theme')`) | `StoreExtension` |
| `app.isMounted` / `app.isBooting` / `app.mountPoint` | SPA shell mount state | `ShellExtension` |
| `app.mount(selector?)` / `app.unmount()` | Mount/unmount the SPA shell | `ShellExtension` |
| `app.snippets` | Named Svelte-component registry for the legacy UI | `SnippetExtension` (`@deprecated`) |
| `app.toast` | App-wide `ToastContext` holder | `LegacyToastExtension` (`@deprecated`) |

`app.snippets` and `app.toast` are transitional bridges that go away once the SPA rewrite gives the page a single Svelte root. `ClientExtension` is flagged for further refactoring — don't over-rely on its exact shape yet.

---

## How the surface stays typed: declaration merging

`kernel/extendableTypes.ts` exports five **empty** interfaces on purpose. Each module that contributes to a registry augments the relevant interface via `declare module`, so the type system knows about keys the kernel itself never imports. There is no runtime code in this file — import it as a type only.

| Interface | Populated by | Keys become… |
|---|---|---|
| `HawkiAppExtensions` | every extension | properties on `app` (the table above) |
| `HawkiConfigSchemas` | each config schema file | typed `app.config.get('namespace')` returns |
| `HawkiResourceSchemas` | each resource schema file | typed `app.restApi.getResource('ai-models')` returns |
| `HawkiDataStores` | each store class | typed `app.stores.get('theme')` / `useStore('theme')` |
| `HawkiPlugins` | each plugin class | typed `app.plugins.get('core')` |

The pattern, from `StoreExtension`:

```ts
declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        stores: WithoutAppExtensionInternals<StoreExtension>;
    }
}
```

`WithoutAppExtensionInternals<T>` strips the `init`/`ready`/`provideProperties` lifecycle members so `app.stores` exposes only `StoreExtension`'s public API, not its extension-plumbing. Adding a key to any of these interfaces next to your class definition is the whole wiring step — see [Extending HAWKI](../../800-Plugins/200-Extending-HAWKI/index.md).

---

## The extensions

| Extension | Owns | Hooks boot stage? | Notes |
|---|---|---|---|
| `ResourceSchemaExtension` | Zod schema registry for resources | — (runs during assembly) | eager-globs `app/schemas/resources/*.schema` |
| `ClientExtension` | HTTP client, connection | `preparation` (fetches connection) | `@todo` not settled |
| `PluginExtension` | plugin discovery + lifecycle | `ready` schedules plugin `boot`/`ready` | auto-discovers `$lib/plugins/**/*.plugin.ts` |
| `ConfigurationExtension` | server config | `preparation` (fetches config) | runs plugin `configSchemas` during init |
| `MigrationExtension` | frontend migrations | on-demand (`apply`) | core plugins only |
| `LocalizationExtension` | locale + labels | `preparation` (locales), `main` (labels) | exposes `app.translator` |
| `ModuleExtension` | feature-module registry | — (runs during assembly) | modules keyed `${plugin}:${name}` |
| `RoutingExtension` | route registry + router | `late` (builds the router) | dispatches `runRoutes` + module routes in `init` |
| `StoreExtension` | data-store registry | `main` (calls each store's `loadData`) | plugins register stores |
| `ShellExtension` | SPA shell mount | `finalization` (mounts + legacy fallback) | see [Modules & Routing](120-Modules-and-Routing.md) |
| `SnippetExtension` | snippet registry | — | `@deprecated` transitional |
| `LegacyToastExtension` | app-wide toast | — | `@deprecated` transitional |

The boot stages themselves are documented in [App Startup](110-App-Startup.md); how plugins drive the per-concern `run*` dispatch is in [Plugin Internals](130-Plugin-Internals.md).

---

## Reaching the app from components

Prefer the dedicated hook in `app/hooks/` over `useApp()` when one exists — it is narrower and (for config) reactive:

| Hook | Returns | Use for |
|---|---|---|
| `useApp()` | `HawkiApp` | extension surfaces with no dedicated hook |
| `useConfig(ns?)` | reactive config namespace (default `'hawki-core'`) | runtime config values |
| `useConnection()` / `useAuthenticatedConnection()` / `useConnectionWithUserInfo()` | connection (narrowed) | auth-state-aware access |
| `useStore(name)` | a typed `DataStore` | shared reactive state |
| `useTranslator()` | `Translator` (`__`, `translate`, …) | user-facing strings |
| `useRestApi()` / `useLinkPreviewApi()` | `app.restApi` / `app.linkPreviewApi` | typed fetches / link previews |

`useApp()` resolves the app from Svelte context (`provideApp()`, set up by the `Shell` component) and falls back to the legacy global registry (`getHawkiApp()`) when no context is set — the fallback is temporary and disappears once all legacy code is context-aware.

---

## Where to go next

| I want to… | Read |
|---|---|
| Understand the boot stages and ordering | [App Startup](110-App-Startup.md) |
| Understand modules, routing, and the SPA shell | [Modules & Routing](120-Modules-and-Routing.md) |
| Understand how plugins are discovered and dispatched | [Plugin Internals](130-Plugin-Internals.md) |
| Add a new app-wide subsystem or a plugin | [Extending HAWKI](../../800-Plugins/200-Extending-HAWKI/index.md) |
| See how config/connection/stores are consumed | [Data Layer](../200-Concepts/130-Data-Layer.md) |
