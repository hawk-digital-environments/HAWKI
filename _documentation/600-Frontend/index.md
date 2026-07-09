# Frontend Overview

:::info[In a migration phase]
We are currently in the middle of migrating the frontend from a legacy vanilla-JS layer to a modern Svelte 5 + TypeScript stack.

The whole system is currently changing pretty rapidly, so do not expect any of the documented features to be stable. If you are contributing, please check the latest code and ask questions in Discord if anything is unclear.
:::

## Philosophy

HAWKI's frontend is in a hybrid transition. Blade templates remain the leading rendering layer: the server renders the page shell, and Svelte components are progressively mounted into it to replace individual UI sections. No new code goes into `public/js/` — all new frontend work lives under `resources/js/` and is processed by Vite.

The Svelte layer is designed to eventually become a full SPA. Until that point, Svelte snippets are embedded into Blade views via a custom `<x-svelte>` component, and new UI code communicates with the remaining legacy vanilla-JS layer through a dedicated bridge. Contributors should always follow the new patterns described in this documentation and treat legacy paths as read-only.

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
├── app.ts                  ← entry point; boots the application via Bootstrapper
├── dependencies.ts         ← lazy dependency loader (Echo/Pusher)
├── svelteSnippetLoader.ts  ← registers Svelte snippets for Blade embedding
├── types.ts                ← top-level shared TypeScript type definitions
├── components/             ← all Svelte components, organized by feature
│   ├── app/                ← app-level concerns (AppContext)
│   ├── chat/               ← chat UI (composer, header, name menu, utils)
│   ├── ui/                 ← primitive UI component library
│   └── util/               ← composable utility components
├── snippets/               ← Blade-embeddable entry point components
├── stores/                 ← reactive singleton stores (*.svelte.ts)
├── schemas/                ← Zod/JSON schemas for config and resource types
├── types.ts                ← shared TypeScript types
├── data/                   ← server data layer
│   ├── api/                ← HTTP client helpers
│   ├── config/             ← app configuration loading and access
│   ├── connection/         ← WebSocket / connection management
│   ├── keychain/           ← encrypted key storage helpers
│   ├── migrations/         ← migration runner (used by frontend migrations)
│   └── resources/          ← typed resource registry and fetching
├── encryption/             ← Web Crypto API helpers
│   ├── symmetric.ts
│   ├── asymmetric.ts
│   ├── hybrid.ts
│   └── utils.ts
├── migrations/             ← frontend migration scripts (auto-discovered)
├── oldUi/                  ← bridge to legacy vanilla-JS layer
└── utils/                  ← shared utilities
    ├── Bootstrapper.ts     ← boot stage orchestration
    ├── flows/              ← pipeline utilities (sync/async/parallel)
    ├── translator.ts       ← i18n helper
    └── ...

resources/css/
├── app.css                 ← @layer declaration order + imports
├── tokens/                 ← CSS custom property definitions
├── layers/                 ← reset and base layer rules
└── utilities.css           ← shared utility classes
```

## How the Pieces Fit Together

`app.ts` instantiates a `Bootstrapper` from `utils/Bootstrapper.ts` and runs a sequence of ordered boot stages that load configuration, establish the server connection, fetch AI models and tools, and hydrate the reactive stores. Once booting is complete, data is available throughout the app via typed singleton stores such as `AiModelStore` and `KeychainStore`. Svelte components are mounted into Blade pages by the `svelteSnippetLoader`, which responds to `<x-svelte>` elements placed by Blade templates. The new chat UI and any other Svelte code that needs to interact with the remaining legacy vanilla-JS layer does so exclusively through `OldUiBridge`, keeping the boundary explicit and containable.

## Where to Go Next

| You want to…                            | Read                           |
|-----------------------------------------|--------------------------------|
| Build a Svelte component                | Basics → Svelte Components     |
| Write CSS                               | Basics → Styling               |
| Fetch data from the server              | Basics → Data Layer            |
| Understand a specific component feature | Components → (that feature)    |
| Add a boot stage or understand startup  | Advanced → App Startup         |
| Use the pipeline utilities              | Advanced → Event System        |
| Bridge new Svelte code to legacy JS     | Advanced → Old UI Bridge       |
| Work with encryption                    | Advanced → Encryption          |
| Create a frontend migration             | Advanced → Frontend Migrations |
