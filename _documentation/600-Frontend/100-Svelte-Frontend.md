# Svelte Frontend

:::info[In a migration phase]
The HAWKI frontend is planned to be rewritten as a full Svelte SPA. This document describes the first step in that direction. We are taking a **hybrid approach**: Blade templates remain the leading rendering layer, but we are progressively migrating UI sections into Svelte components that will later become part of the main SPA. **Do not add new code to the legacy vanilla-JS layer** (`public/js/`). All new frontend work must follow the patterns described here.

The whole system is currently changing pretty rapidly, so do not expect any of the documented features to be stable. If you are contributing, please check the latest code and ask questions in Discord if anything is unclear.
:::

---

## Technology Stack

- **[Svelte 5](https://svelte.dev/)** with the Runes API (`$state`, `$derived`, `$props`, …) — no Options API / legacy Svelte 4 syntax
- **TypeScript** — every `.svelte` and `.ts` file must be typed; avoid `any` where possible
- **Vite** as the bundler (configured in `vite.config.js` / `svelte.config.js`)
- **CSS custom properties + cascade layers** — design tokens in `resources/css/tokens/`, component styles in Svelte `<style>` blocks; no Tailwind, no CSS-in-JS
- **`class-variance-authority` (CVA)** — declarative variant→class mapping for components that expose style-driving props (`size`, `intent`, …); `cx` re-exported from CVA is used internally by `mergeProps` for class merging

---

## Directory Structure

```
resources/js/
├── components/       ← Reusable, general-purpose Svelte components
│   └── ui/           ← Low-level primitive components (no business logic)
├── snippets/         ← Top-level Blade-embeddable entry components (one per page slot)
├── stores/           ← Svelte 5 reactive store classes (*.svelte.ts)
├── types.ts          ← Shared TypeScript type definitions
└── utils/            ← Shared utilities
```

> **Note:** Earlier versions of the Contributing guide incorrectly documented these paths with an extra `svelte/` path segment (e.g. `resources/js/svelte/snippets/`). The correct paths do not include that segment.

---

## The Hybrid Approach — Snippets

:::caution[Temporary Architecture]
The snippet-based hybrid approach is a transitional solution for the current Blade/Svelte coexistence. It will be replaced once the SPA rewrite finalises a single-root Svelte frontend in HAWKI Version 3.0.0. When building new features, favour stores and context patterns that will work in both the current hybrid and the future SPA architecture. Treat `AppContext` and any workarounds based on snippet isolation as read-only — do not build new patterns on top of them.
:::

Until the full SPA rewrite is complete, Svelte is integrated into the server-rendered Blade UI through the concept of **snippets**. A snippet is a regular Svelte component that is mounted inside a server-rendered Blade template, acting as a self-contained "mini-app" for a specific section of the page. Over time these snippets will grow into the building blocks of the final SPA.

### Why snippets are isolated

Each snippet is its own separately mounted Svelte application. There is no shared Svelte component tree or Svelte context across snippets on the same page. Two snippets rendered side by side in the DOM cannot communicate through `setContext`/`getContext` because they have different component roots.

**Stores cross snippet boundaries automatically.** Svelte stores implemented as module-level singletons (exported from a `.svelte.ts` file) are shared because JavaScript modules are singletons within a page load. Any snippet that imports the same store module reads and writes the same reactive instance.

### `AppContext` — simulated global context

`resources/js/components/app/AppContext.svelte.ts` works around the snippet isolation problem by maintaining a module-level singleton instead of using Svelte's real context mechanism. Any snippet can call `useAppContext()` to get the shared instance, even though there is no common Svelte ancestor.

This is explicitly marked `@deprecated`. It exists only to bridge the gap until the SPA rewrite replaces snippet-per-slot mounting with a single Svelte root. Once that happens, `AppContext` will be replaced by a proper `createAppContext()` that uses Svelte's real context API.

The main thing `AppContext` currently holds is a reference to the shared `ToastContext`, so the `Toaster` component can be mounted exactly once regardless of how many snippets are on the page.

### `LegacySharedContent.svelte` — the page-level singleton host

`resources/js/snippets/LegacySharedContent.svelte` is a special snippet that is auto-injected at the top of every page during bootstrap. Its job is to host UI elements that must exist exactly once per page but cannot live in every snippet independently.

On mount it sets `AppContext.legacySharedContentLoaded = true` and renders the shared `Toaster` component. If you attempt to push a toast when this snippet has not been loaded, the application throws rather than silently failing.

Any page-level singleton UI that faces the same "one instance per page" constraint belongs here, not inside a regular snippet.

### Currently active snippets

| Snippet                      | Purpose                                                                      |
|------------------------------|------------------------------------------------------------------------------|
| `ChatComposer.svelte`        | Main chat input: message composition, file attachments, model/tool selection |
| `ChatHeader.svelte`          | Chat header bar with conversation controls                                   |
| `ChatSidebarButton.svelte`   | Sidebar toggle/open button                                                   |
| `AttachmentDropdown.svelte`  | Attachment preview and management dropdown                                   |
| `LegacySharedContent.svelte` | Auto-injected; hosts the shared Toaster and other page-level singletons      |

### Embedding Svelte in Blade: the `<x-svelte>` component

The bridge between Blade and Svelte is the `<x-svelte>` Blade component (implemented in `app/Services/Frontend/Connection/View/SvelteComponent.php`). It renders a `<svelte-snippet>` custom HTML element, which `svelteSnippetLoader.ts` picks up and mounts the matching Svelte component inside.

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

The `type` attribute is the filename of the Svelte component inside `resources/js/snippets/`, without the `.svelte` extension. Props are JSON-encoded by the Blade component automatically. Any extra HTML attributes (`class`, `id`, `data-*`, …) are forwarded verbatim to the rendered element.

It discovers all files in `snippets/` automatically at build time, so **no manual import or registration is needed** when you add a new snippet.

**Lifecycle:** the component is mounted when the element enters the DOM, destroyed when it leaves, and destroyed + remounted whenever the `type` or `props` attribute changes at runtime. Treat snippets as stateless from the outside — internal state is reset on every remount.

### Adding a new snippet

1. Create a `.svelte` file in `resources/js/snippets/`, e.g. `resources/js/snippets/MyWidget.svelte`.
2. Use it in Blade: `<x-svelte type="MyWidget" />`.

No imports or registrations are needed anywhere else.

### The `root` prop

Every snippet automatically receives a `root` prop that is a reference to the `<svelte-snippet>` DOM element itself. Use it to:

- Read additional HTML attributes set by Blade
- Dispatch custom DOM events to communicate state changes back to legacy vanilla-JS code

```svelte
<script lang="ts">
    import {HTMLSvelteSnippetElement} from '../svelteSnippetLoader.js';

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
