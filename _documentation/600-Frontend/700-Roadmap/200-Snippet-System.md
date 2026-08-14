# Snippet System

:::danger[Being phased out]
The snippet system is the Blade-to-Svelte bridge the SPA shell replaces. It is `@deprecated` and scheduled for removal in the next release. New pages must mount via the SPA shell (`#hawki-app` + `RouterView`), not via snippets. Do not write new snippets. See [Technical Debt](../900-Technical-Debt.md).
:::

Until the full SPA rewrite is complete, Svelte is integrated into the server-rendered Blade UI through the concept of **snippets**. A snippet is a regular Svelte component that is mounted inside a server-rendered Blade template, acting as a self-contained "mini-app" for a specific section of the page. Over time the SPA shell and the router have taken over this role.

## When the snippet path is taken

`ShellExtension.ready()` calls `mount()` to mount the SPA `Shell` into `#hawki-app`. If that element is absent (a legacy page without a shell mount point), `mount()` returns `false` and `ShellExtension` falls back to `legacyInitializeSnippetApps` on `onStagePassed('finalization')`. Pages with `#hawki-app` never run the snippet path. See [Architecture → Modules & Routing](../300-Architecture/120-Modules-and-Routing.md).

## How snippets are registered and mounted

`legacy/legacyInitializeSnippetApps.ts` (triggered by `ShellExtension` only in the legacy fallback) lazy-globs every `.svelte` file under `plugins/core/snippets/` and registers each into `app.snippets` under its file base name. Once the DOM is ready it defines the `<svelte-snippet>` custom element and injects a `LegacySharedContent` snippet as the first child of `<body>` (the one-per-page host for shared UI like the `Toaster`).

The bridge between Blade and a snippet is the `<x-svelte>` Blade component (`app/Services/Frontend/View/SvelteComponent.php`). It renders a `<svelte-snippet>` custom HTML element, which the loader resolves through the snippet registry and mounts the matching Svelte component inside.

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

**Lifecycle:** the component is mounted when the element enters the DOM, destroyed when it leaves, and destroyed + remounted whenever the `type` or `props` attribute changes at runtime. Treat snippets as stateless from the outside — internal state is reset on every remount.

## Currently active snippets

The snippets live in `resources/js/plugins/core/snippets/`:

| Snippet | Purpose |
|---|---|
| `ChatComposer.svelte` | Main chat input: message composition, file attachments, model/tool selection |
| `ChatHeader.svelte` | Chat header bar with conversation controls |
| `ChatSidebarButton.svelte` | Sidebar toggle/open button |
| `AttachmentDropdown.svelte` | Attachment preview and management dropdown |
| `MessageBody.svelte` | Rendered message body |
| `LegacySharedContent.svelte` | Auto-injected; hosts the shared `Toaster` and other page-level singletons |

## `SnippetExtension` and `LegacyToastExtension`

Two kernel extensions exist solely to support the snippet path:

- `SnippetExtension` (`legacy/SnippetExtension.ts`) owns `app.snippets`, the named Svelte-component registry the snippet loader reads from. `@deprecated`.
- `LegacyToastExtension` (`legacy/LegacyToastExtension.ts`) owns `app.toast`, the app-wide `ToastContext` holder for pages that render the `Toaster` via the `LegacySharedContent` snippet rather than the SPA shell. `@deprecated`.

On SPA pages the toast context is set up by `Shell.svelte` directly (`createToastContext()`), so neither extension is used.

## The `root` prop

Every snippet automatically receives a `root` prop that is a reference to the `<svelte-snippet>` DOM element itself. Use it to read additional HTML attributes set by Blade or dispatch custom DOM events to communicate state changes back to legacy vanilla-JS code.

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

## The custom element class

`svelteSnippetLoader.ts` defines the `HTMLSvelteSnippetElement` custom element class (its behaviour — mount/destroy/remount on attribute change). The `customElements.define('svelte-snippet', …)` call itself lives in `legacyInitializeSnippetApps.ts`, not in `svelteSnippetLoader.ts`.
