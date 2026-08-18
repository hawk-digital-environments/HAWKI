# Core Plugin

The `core` plugin (`resources/js/plugins/core/core.plugin.ts`) is HAWKI's first-party feature bundle. It wires together the foundational, always-on features of the frontend.

## What it registers

`CorePlugin` implements `HawkiCorePlugin` and uses four hooks:

| Hook | What it registers |
|---|---|
| `migrations` | Lazy-globs `plugins/core/migrations/**/*.ts` and hands the loaders to `MigrationExtension` (keychain/encryption format upgrades). |
| `modules` | Registers `ChatModule` (see [Chat Module](110-Chat-Module.md)). |
| `routes` | Declares the root `/` route, lazily loading `pages/Index.svelte`. |
| `stores` | Registers the six core stores: `KeychainStore`, `AiHandleStore`, `AiModelStore`, `AiToolStore`, `SystemPromptStore`, `ThemeStore`. |

It does not implement `boot` or `ready` — feature setup that needs a boot stage lives in the relevant extension or the module, not the plugin.

## Directory layout

```
plugins/core/
├── core.plugin.ts          the plugin entry point (auto-discovered)
├── modules/chat/            the chat feature module (composer, pages, components)
├── pages/                   routed page components (Index.svelte)
├── snippets/                legacy Blade-embeddable Svelte entry components (being phased out — see Roadmap)
├── schemas/resources/       plugin-owned Zod resource schemas (ai-models, ai-tools, …)
└── migrations/              frontend migrations (after_passkey/…)
```

## Where to go next

| I want to… | Read |
|---|---|
| Understand the chat composer | [Chat Module](110-Chat-Module.md) |
| Understand the stores it registers | [Concepts → Stores](../../../600-Frontend/200-Concepts/120-Stores.md) |
| Understand frontend migrations | [Concepts → Frontend Migrations](../../../600-Frontend/200-Concepts/170-Frontend-Migrations.md) |
| Author a plugin of your own | [Extending HAWKI](../../200-Extending-HAWKI/index.md) |
