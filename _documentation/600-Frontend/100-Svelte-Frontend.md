# Svelte Frontend

:::info[In a migration phase]
The HAWKI frontend is planned to be rewritten as a full Svelte SPA. This document describes the first step in that direction. We are taking a **hybrid approach**: Blade templates remain the leading rendering layer, but we are progressively migrating UI sections into Svelte components that will later become part of the main SPA. **Do not add new code to the legacy vanilla-JS layer** (`public/js/`). All new frontend work must follow the patterns described here.

The whole system is currently changing pretty rapidly, so do not expect any of the documented features to be stable. If you are contributing, please check the latest code and ask questions in Discord if anything is unclear.
:::

---

## Technology Stack

- **[Svelte 5](https://svelte.dev/)** with the Runes API (`$state`, `$derived`, `$props`, …) — no Options API / legacy Svelte 4 syntax
- **TypeScript** — every `.svelte` and `.ts` file must be typed; avoid `any` where possible
- **Vite** as the bundler (configured in `vite.config.ts` / `svelte.config.js`)
- **CSS custom properties + cascade layers** — design tokens in `resources/css/tokens/`, component styles in Svelte `<style>` blocks; no Tailwind, no CSS-in-JS
- **`class-variance-authority` (CVA)** — declarative variant→class mapping for components that expose style-driving props (`size`, `intent`, …); `cx` re-exported from CVA is used internally by `mergeProps` for class merging

---

## Directory Structure

```
resources/js/
├── app.ts                ← entry point: createApp(extensions) + bootstrapper.run()
├── app/                  ← app-level hooks + app-owned schemas
│   ├── hooks/            ← useApp, useConfig, useConnection, useStore, useTranslator, useRestApi
│   └── schemas/          ← config + resource schemas (augment Hawki*Schemas)
├── kernel/               ← the extension-assembled app core (see Advanced → The App & Kernel)
├── plugins/core/         ← the (only) first-party plugin
│   ├── snippets/         ← Blade-embeddable entry components (one per page slot)
│   ├── stores/           ← reactive stores (*.svelte.ts)
│   ├── schemas/          ← plugin-owned resource schemas
│   └── modules/          ← feature modules (chat, …)
├── legacy/               ← bridge to the legacy vanilla-JS layer
├── components/           ← reusable Svelte components
│   ├── ui/               ← low-level primitives (no business logic)
│   └── util/             ← composable utility components
└── utils/                ← shared utilities (flows, debounce, …)
```

Path aliases: **`$lib` = `resources/js/`**, **`$plugins` = `resources/js/plugins/`** (in `tsconfig.json` + `vite.config.ts`).

---

## The Hybrid Approach — Snippets

:::caution[Temporary Architecture]
The snippet-based hybrid approach is a transitional solution for the current Blade/Svelte coexistence. It will be replaced once the SPA rewrite finalises a single-root Svelte frontend in HAWKI Version 3.0.0. When building new features, favour stores and context patterns that will work in both the current hybrid and the future SPA architecture.
:::

Until the full SPA rewrite is complete, Svelte is integrated into the server-rendered Blade UI through the concept of **snippets**. A snippet is a regular Svelte component that is mounted inside a server-rendered Blade template, acting as a self-contained "mini-app" for a specific section of the page. Over time these snippets will grow into the building blocks of the final SPA.

### Why snippets are isolated

Each snippet is its own separately mounted Svelte application. There is no shared Svelte component tree or Svelte context across snippets on the same page. Two snippets rendered side by side in the DOM cannot communicate through `setContext`/`getContext` because they have different component roots.

**Stores and the app cross snippet boundaries automatically.** Stores are registered on the kernel's `app.stores` registry (a module-level singleton), and the `HawkiApp` itself is a single instance. Any snippet that calls `useStore('…')` or `useApp()` reads the same reactive instance, because they all resolve through the one app assembled in `app.ts`.

### Reaching the app

Components reach the app through the hooks in `app/hooks/` — `useApp()`, `useConfig()`, `useConnection()`, `useStore()`, `useTranslator()`, `useRestApi()`. `useApp()` resolves the `HawkiApp` from Svelte context (set up via `provideApp()` near a Svelte root) and falls back to the legacy global registry (`getHawkiApp()`) when no context is available. Prefer the specific hooks over `useApp()` when one fits. See [Advanced → The App & Kernel](../600-Frontend/600-Advanced/110-App-and-Kernel.md) and [Data Layer](../600-Frontend/300-Data/index.md).

### `LegacySharedContent.svelte` — the page-level singleton host

`resources/js/plugins/core/snippets/LegacySharedContent.svelte` is a special snippet that is auto-injected at the top of every page during bootstrap (in `app.ts`, on the `late` boot stage). Its job is to host UI elements that must exist exactly once per page but cannot live in every snippet independently.

On mount it renders the shared `Toaster` component. Any page-level singleton UI that faces the same "one instance per page" constraint belongs here, not inside a regular snippet.

### Currently active snippets

The snippets are registered by the `core` plugin, which eager-globs `plugins/core/snippets/**/*.svelte` in its `boot()` hook. The active set:

| Snippet                      | Purpose                                                                      |
|------------------------------|------------------------------------------------------------------------------|
| `ChatComposer.svelte`        | Main chat input: message composition, file attachments, model/tool selection |
| `ChatHeader.svelte`          | Chat header bar with conversation controls                                   |
| `ChatSidebarButton.svelte`   | Sidebar toggle/open button                                                   |
| `AttachmentDropdown.svelte`  | Attachment preview and management dropdown                                   |
| `MessageBody.svelte`         | Rendered message body                                                        |
| `LegacySharedContent.svelte` | Auto-injected; hosts the shared Toaster and other page-level singletons      |

### Embedding Svelte in Blade: the `<x-svelte>` component

The bridge between Blade and Svelte is the `<x-svelte>` Blade component (implemented in `app/Services/Frontend/View/SvelteComponent.php`). It renders a `<svelte-snippet>` custom HTML element, which the core plugin resolves through the snippet registry and mounts the matching Svelte component inside.

```blade
{{-- Minimal --}}
<x-svelte type="ChatInput" />

{{-- With PHP props and extra HTML attributes --}}
<x-svelte
    type="ChatInput"
    :props="['readonly' => true]"
    class="my-class"
/>
```

The `type` attribute is the filename of the Svelte component inside `resources/js/plugins/core/snippets/`, without the `.svelte` extension. Props are JSON-encoded by the Blade component automatically. Any extra HTML attributes (`class`, `id`, `data-*`, …) are forwarded verbatim to the rendered element.

The `<svelte-snippet>` custom element is defined by the core plugin on the `finalization` boot stage (`customElements.define('svelte-snippet', HTMLSvelteSnippetElement)` in `legacy/svelteSnippetLoader.ts`).

**Lifecycle:** the component is mounted when the element enters the DOM, destroyed when it leaves, and destroyed + remounted whenever the `type` or `props` attribute changes at runtime. Treat snippets as stateless from the outside — internal state is reset on every remount.

### Adding a new snippet

1. Create a `.svelte` file under `resources/js/plugins/core/snippets/` (for core features), e.g. `MyWidget.svelte`.
2. Use it in Blade: `<x-svelte type="MyWidget" />`.

No imports or registrations are needed — the core plugin's `boot()` glob picks up every `.svelte` file in that directory. (When writing your own plugin, place snippets in your plugin's `snippets/` directory and register them from the plugin's `boot()` hook; see [Writing a Plugin](../600-Frontend/600-Advanced/130-Writing-a-Plugin.md).)

### The `root` prop

Every snippet automatically receives a `root` prop that is a reference to the `<svelte-snippet>` DOM element itself. Use it to:

- Read additional HTML attributes set by Blade
- Dispatch custom DOM events to communicate state changes back to legacy vanilla-JS code

```svelte
<script lang="ts">
    import {HTMLSvelteSnippetElement} from '$lib/legacy/svelteSnippetLoader.js';

    interface Props {
        root: HTMLSvelteSnippetElement;
    }

    const {root}: Props = $props();

    function notifyLegacy(value: string) {
        root.dispatchEvent(new CustomEvent('myWidget:change', {detail: {value}, bubbles: true}));
    }
</script>
```

---
