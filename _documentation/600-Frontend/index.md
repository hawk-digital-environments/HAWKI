# Frontend Overview

HAWKI's frontend is a **Svelte 5 + TypeScript** application assembled from a small extension-based kernel. It is mid-migration from a Blade + vanilla-JS UI toward a single-page Svelte app; the new shell and router are the primary path, the legacy snippet bridge is being phased out.

## How the docs are organised

| Section                                 | What you find there                                                                                                             |
|-----------------------------------------|---------------------------------------------------------------------------------------------------------------------------------|
| [Concepts](200-Concepts/index.md)       | The kernel, boot sequence, modules, plugins, routing, stores, styling — one page per concept. The hub everything else links to. |
| [Components](400-Components/index.md)   | Catalogue of the component library (primitives, utilities, icons).                                                              |
| [Reference](600-Reference/index.md)     | Lookup catalogues (shared utilities). Code is truth.                                                                            |
| [Technical Debt](900-Technical-Debt.md) | The violations register, audience-tagged.                                                                                       |

Plugin and extension authoring — both frontend and backend — lives in its own top-level section: [Plugins](../800-Plugins/index.md). That section also covers the first-party core plugins.

## Where things live

```
resources/js/
├── app.ts                  entry point: createApp(extensions) + bootstrapper.run()
├── types.ts                shared TypeScript types
├── app/                    app-level
│   ├── components/         Shell.svelte (SPA root) + app-wide components
│   ├── contexts/           (reserved)
│   ├── hooks/              useApp, useConfig, useConnection, useStore, useTranslator, useApi
│   └── schemas/            app-owned Zod schemas (config + resources)
├── kernel/                 the extension-assembled app core
│   ├── HawkiApp.ts         createApp(), HawkiAppExtension contract
│   ├── Bootstrapper.ts     six-stage boot orchestration
│   ├── extendableTypes.ts  empty interfaces populated by declaration merging
│   ├── api/                REST / JSON:API client, transport, URI builder
│   ├── client/             ClientExtension: HTTP client + connection
│   ├── config/             ConfigurationExtension: namespaced, Zod-validated config
│   ├── encryption/         Web Crypto wrappers (symmetric / asymmetric / hybrid)
│   ├── keychain/           encrypted key storage helpers
│   ├── localization/       LocalizationExtension + translator
│   ├── migrations/         MigrationExtension: frontend migration runner
│   ├── modules/            ModuleExtension: feature-module registry
│   ├── plugins/            PluginExtension + PluginBootstrapper: plugin discovery + dispatch
│   ├── resources/          ResourceSchemaExtension: Zod schema registry for resources
│   ├── routing/            RoutingExtension: route registry + router build
│   ├── shell/              ShellExtension: SPA shell mount + legacy snippet fallback
│   └── stores/             StoreExtension: data-store registry
├── plugins/core/           the (only) first-party plugin
│   ├── core.plugin.ts      registers stores, modules, routes, migrations
│   ├── modules/chat/        chat feature module (composer, pages, components)
│   ├── pages/              routed page components
│   ├── snippets/           legacy Blade-embeddable Svelte entry components (being phased out)
│   ├── schemas/            plugin-owned resource schemas
│   └── migrations/         frontend migrations (after_passkey/…)
├── legacy/                 bridge to the legacy vanilla-JS layer (being phased out)
│   ├── OldUiBridge.svelte.ts
│   ├── OldUiMessageHistory.svelte.ts
│   ├── svelteSnippetLoader.ts
│   ├── legacyInitializeSnippetApps.ts
│   ├── dependencies.ts
│   └── SnippetExtension.ts / LegacyToastExtension.ts
├── components/             Svelte component library (slated for npm extraction)
│   ├── ui/                 primitive component library (no business logic)
│   └── util/               composable utility components
└── utils/                  shared utilities (flows, debounce, strings, transitions, …)

resources/css/
├── app.css                 @layer comments + token/layer imports
├── tokens/                 CSS custom property definitions (per-concern files)
├── layers/                 reset and base layer rules
└── utilities.css           shared utility classes
```

Path aliases: **`$lib` = `resources/js/`**, **`$plugins` = `resources/js/plugins/`** (configured in `tsconfig.json` + `vite.config.ts`). There is no `$components` alias.

## Where to start

| I want to…                                          | Start here                                                                                                     |
|-----------------------------------------------------|----------------------------------------------------------------------------------------------------------------|
| Understand the mental model of the app + kernel     | [Concepts → The App & Kernel](200-Concepts/120-App-and-Kernel/index.md)                                        |
| Walk a routed page end-to-end (from boot to render) | [Concepts → App Startup](200-Concepts/120-App-and-Kernel/110-App-Startup.md)                                   |
| Write or restyle a Svelte component                 | [Concepts → Svelte Components](200-Concepts/100-Svelte-Components.md) → [Styling](200-Concepts/110-Styling.md) |
| Read or write shared reactive state                 | [Concepts → Stores](200-Concepts/130-Stores.md)                                                                |
| Fetch data from the server                          | [Concepts → Data Layer](200-Concepts/140-Data-Layer.md)                                                        |
| Translate a string                                  | [Concepts → Translations](200-Concepts/150-Translations.md)                                                    |
| Use a UI primitive                                  | [Components → UI Primitives](400-Components/100-UI-Primitives.md)                                              |
| Understand the composer (chat input)                | [Plugins → Core Plugins → Chat Module](../800-Plugins/100-Bundled-Plugins/100-Core/110-Chat-Module.md)         |
| Add a feature (stores, schemas, modules, routes)    | [Plugins → Extending HAWKI](../800-Plugins/200-Extending-HAWKI/index.md)                                       |
| Work with client-side encryption                    | [Concepts → Encryption](200-Concepts/170-Encryption.md)                                                        |
| Understand frontend migrations                      | [Concepts → Frontend Migrations](200-Concepts/180-Frontend-Migrations.md)                                      |
| Understand routing                                  | [Concepts → Routing](200-Concepts/190-Routing.md)                                                              |
