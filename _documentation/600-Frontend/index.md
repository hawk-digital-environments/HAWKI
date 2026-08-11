# Frontend Overview

:::info[In a migration phase]
We are currently in the middle of migrating the frontend from a legacy vanilla-JS layer to a modern Svelte 5 + TypeScript stack.

The whole system is currently changing pretty rapidly, so do not expect any of the documented features to be stable. If you are contributing, please check the latest code and ask questions in Discord if anything is unclear.
:::

## Philosophy

HAWKI's frontend is in a hybrid transition. Blade templates remain the leading rendering layer: the server renders the page shell, and Svelte components are progressively mounted into it to replace individual UI sections. No new code goes into `public/js/` — all new frontend work lives under `resources/js/` and is processed by Vite.

The Svelte layer is designed to eventually become a full SPA. Until that point, Svelte snippets are embedded into Blade views via a custom `<svelte-snippet>` element, and new UI code communicates with the remaining legacy vanilla-JS layer through a dedicated bridge. Contributors should always follow the new patterns described in this documentation and treat legacy paths as read-only.

## Technology Stack

| Technology                                               | Role                                                     |
|----------------------------------------------------------|----------------------------------------------------------|
| **Svelte 5** (Runes API: `$state`, `$derived`, `$props`) | Component framework and reactivity model                 |
| **TypeScript**                                           | Type safety across all new frontend code                 |
| **Vite**                                                 | Build tool and dev server                                |
| **CSS custom properties + cascade layers**               | Design tokens and style isolation                        |
| **class-variance-authority (CVA)**                       | Variant-based component class composition                |
| **Web Crypto API**                                       | Client-side symmetric, asymmetric, and hybrid encryption |

## Directory Map

```
resources/js/
├── app.ts                  ← entry point: createApp(extensions) + bootstrapper.run()
├── types.ts                ← shared TypeScript types
├── app/                    ← app-level: hooks + app-owned schemas
│   ├── hooks/              ← useApp, useConfig, useConnection, useStore, useTranslator, useApi
│   └── schemas/            ← config + resource schemas owned by the app (augment Hawki*Schemas)
├── kernel/                 ← the extension-assembled app core
│   ├── HawkiApp.ts         ← createApp(), HawkiAppExtension contract
│   ├── Bootstrapper.ts     ← six-stage boot orchestration
│   ├── extendableTypes.ts  ← empty interfaces populated by declaration merging
│   ├── api/                ← REST / JSON:API client, transport, URI builder
│   ├── client/             ← ClientExtension: HTTP client + connection
│   ├── config/             ← ConfigurationExtension: namespaced, Zod-validated config
│   ├── encryption/         ← Web Crypto wrappers (symmetric / asymmetric / hybrid)
│   ├── keychain/           ← encrypted key storage helpers
│   ├── localization/       ← LocalizationExtension + translator
│   ├── migrations/         ← MigrationExtension: frontend migration runner
│   ├── modules/            ← ModuleExtension: feature-module registry
│   ├── plugins/            ← PluginExtension + PluginBootstrapper: plugin discovery + dispatch
│   ├── resources/          ← ResourceSchemaExtension: Zod schema registry for resources
│   ├── routing/            ← routing (not yet wired — v3.0.0)
│   └── stores/             ← StoreExtension: data-store registry
├── plugins/core/           ← the (only) first-party plugin
│   ├── core.plugin.ts      ← registers stores, snippets, migrations
│   ├── stores/             ← reactive stores (*.svelte.ts)
│   ├── schemas/            ← plugin-owned resource schemas
│   ├── snippets/           ← Blade-embeddable Svelte entry components
│   └── modules/            ← feature modules (chat, …)
├── legacy/                 ← bridge to the legacy vanilla-JS layer
│   ├── OldUiBridge.svelte.ts
│   ├── OldUiMessageHistory.svelte.ts
│   └── svelteSnippetLoader.ts
├── components/             ← Svelte components
│   ├── ui/                 ← primitive component library (no business logic)
│   └── util/               ← composable utility components
└── utils/                  ← shared utilities (flows, debounce, …)

resources/css/
├── app.css                 ← @layer declaration order + imports
├── tokens/                 ← CSS custom property definitions
├── layers/                 ← reset and base layer rules
└── utilities.css           ← shared utility classes
```

Path aliases: **`$lib` = `resources/js/`**, **`$plugins` = `resources/js/plugins/`** (configured in `tsconfig.json` + `vite.config.ts`). There is no `$components` alias.

## How the Pieces Fit Together

`app.ts` builds the application by calling `createApp(bootstrapper, […extensions])`, which assembles a `HawkiApp` from an ordered list of extensions — each contributing one subsystem (config, client, stores, plugins, …) as a typed property on `app`. The `Bootstrapper` then runs six ordered boot stages that fetch connection/config, load stores and translation labels, and finally mount the Svelte layer. See [Advanced → The App & Kernel](600-Advanced/110-App-and-Kernel.md) and [Advanced → App Startup](600-Advanced/100-App-Startup.md).

Components do not import stores or config directly. They reach the app through hooks in `app/hooks/` — `useApp()`, `useConfig()`, `useConnection()`, `useStore()`, `useTranslator()`, `useRestApi()` — which return typed, often reactive values. See [Data Layer](300-Data/index.md) and [Stores](300-Data/100-Stores.md).

Svelte components are mounted into Blade pages through the `<svelte-snippet>` custom element (defined by the core plugin on the `finalization` stage), which resolves a registered snippet by name. The snippets themselves live in `plugins/core/snippets/` and are registered by the core plugin. New Svelte code that needs to interact with the remaining legacy vanilla-JS layer does so exclusively through `OldUiBridge` (in `legacy/`), keeping the boundary explicit and containable.

## Where to Go Next

| You want to…                            | Read                           |
|-----------------------------------------|--------------------------------|
| Build a Svelte component                | Basics → Svelte Components     |
| Write CSS                               | Basics → Styling               |
| Fetch data from the server              | Basics → Data Layer            |
| Understand a specific component feature | Components → (that feature)    |
| Understand the app architecture         | Advanced → The App & Kernel    |
| Add a boot stage or understand startup  | Advanced → App Startup         |
| Write a plugin or extension             | Advanced → Writing a Plugin    |
| Use the pipeline utilities              | Advanced → Event System        |
| Bridge new Svelte code to legacy JS     | Advanced → Old UI Integration  |
| Work with encryption                    | Advanced → Encryption          |
| Create a frontend migration             | Advanced → Frontend Migrations |
