# Frontend Overview

HAWKI's frontend is a **Svelte 5 + TypeScript** application assembled from a small extension-based kernel. It is mid-migration from a Blade + vanilla-JS UI toward a single-page Svelte app; the new shell and router are the primary path, the legacy snippet bridge is being phased out.

:::info[Migration phase]
The SPA shell (`ShellExtension` + `RouterView`) is the direction. Today both systems coexist: pages with a `#hawki-app` mount point run the routed SPA; legacy pages fall back to the snippet system. The snippet system and legacy bridge are scheduled for removal in the next release. New code targets the SPA; treat snippet/legacy paths as read-only. See [Roadmap](700-Roadmap/index.md) for the transitional scaffolding.
:::

## How the docs are organised

| Section | What you find there |
|---|---|
| [Tutorials](100-Tutorials/index.md) | One concrete scenario, walked end-to-end. The cleanest path in the codebase. |
| [Concepts](200-Concepts/index.md) | "How do I use pattern X" — one page per pattern. The hub everything else links to. |
| [Architecture](300-Architecture/index.md) | The kernel, boot sequence, modules & routing, plugin internals. Contributor-level. |
| [Components](400-Components/index.md) | Catalogue of the component library (primitives, utilities, icons). |
| [Core Plugins](500-Core-Plugins/index.md) | The first-party plugins and their feature modules. Currently only `core`. |
| [Reference](600-Reference/index.md) | Lookup catalogues (shared utilities). Code is truth. |
| [Roadmap](700-Roadmap/index.md) | Transitional scaffolding being phased out (legacy bridge, snippet system). |
| [Technical Debt](900-Technical-Debt.md) | The violations register, audience-tagged. |

Authoring plugins and extensions touches frontend and backend alike — that content lives in its own top-level section: [Extending HAWKI](../700-Extending-Hawki/index.md).

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

| I want to… | Start here |
|---|---|
| Understand the mental model of the app + kernel | [Architecture → The App & Kernel](300-Architecture/100-App-and-Kernel.md) |
| Walk a routed page end-to-end | [Tutorials → Life of a Routed Page](100-Tutorials/100-Life-of-a-Routed-Page.md) |
| Write or restyle a Svelte component | [Concepts → Svelte Components](200-Concepts/100-Svelte-Components.md) → [Styling](200-Concepts/110-Styling.md) |
| Read or write shared reactive state | [Concepts → Stores](200-Concepts/120-Stores.md) |
| Fetch data from the server | [Concepts → Data Layer](200-Concepts/130-Data-Layer.md) |
| Translate a string | [Concepts → Translations](200-Concepts/140-Translations.md) |
| Use a UI primitive | [Components → UI Primitives](400-Components/100-UI-Primitives.md) |
| Understand the composer (chat input) | [Core Plugins → Core → Chat Module](500-Core-Plugins/100-Core/110-Chat-Module.md) |
| Add a feature (stores, schemas, modules, routes) | [Extending HAWKI](../700-Extending-Hawki/index.md) |
| Work with client-side encryption | [Concepts → Encryption](200-Concepts/160-Encryption.md) |
| Understand frontend migrations | [Concepts → Frontend Migrations](200-Concepts/170-Frontend-Migrations.md) |
| Bridge to the legacy layer (transitional) | [Roadmap → Legacy UI Bridge](700-Roadmap/100-Legacy-UI-Bridge.md) |
