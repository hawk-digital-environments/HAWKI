# Temporary Constructs

:::warning[Deprecated — transition scaffolding]
Everything on this page is temporary backend scaffolding from HAWKI's transition from a Blade-rendered MVC application to a pure API server for a Svelte 5 SPA. New backend work must target the API layer; Blade is read-only except for the page shell. Do not introduce new dependencies on any construct listed here.
:::

HAWKI's backend is in a structural transition: from a server-rendered Blade MVC to a pure API server feeding a Svelte SPA. Both worlds coexist in the current codebase. These constructs are the bridge scaffolding; they will be removed when the SPA migration is complete.

## Svelte snippet bridge

The `<x-svelte>` Blade component (backed by `App\Services\Frontend\View\SvelteComponent`) emits a `<svelte-snippet>` custom HTML element. On the browser side, `svelteSnippetLoader.ts` discovers these elements and mounts the matching Svelte component into each one. This is the primary mechanism for embedding Svelte UI into Blade page shells.

`LegacySharedContent.svelte` is automatically injected once per page as a page-level singleton; it provides global UI like the notification toaster.

## `EarlyFrontendBridge`

`App\Services\Frontend\View\EarlyFrontendBridge` is a Blade component (`<x-early-frontend-bridge />`) placed as early in the `<head>` as possible. It renders an inline `<script>` that sets up `window.waitUntilReady` and `window.waitUntilBootstrap` as queue stubs. Third-party scripts injected into the page head can call these functions before the HAWKI JS bundle loads; the queued callbacks are handed to the real implementations once the bundle initialises.

## `AssetCacheBustingUrlGenerator`

Non-Vite assets (fonts, legacy icons, etc.) are served with a content-hash query parameter appended by `AssetCacheBustingUrlGenerator`. Vite-processed assets are excluded because Vite already produces content-hashed filenames.
