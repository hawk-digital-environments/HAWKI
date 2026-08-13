# v%%VERSION%%

### What's New

[//]: # (- The main new features and changes in this version.)

### Quality of Life

- API responses now carry richer error information (status code, server-provided error title/details) so failures surface more useful feedback instead of a generic error.

### Bugfix

[//]: # (- List of bugs that have been fixed in this version.)

### Internals

- Introduced a **kernel + extension architecture** for the frontend: `HawkiApp` is now assembled at startup from independent, ordered extensions (config, HTTP client, plugins, migrations, localization, modules, routing, stores, shell, legacy snippets/toast) instead of one bootstrap script. Each extension contributes typed properties to `app.*` via declaration merging. See `_documentation/600-Frontend/600-Advanced/110-App-and-Kernel.md`.
- Added a **plugin system** (`resources/js/kernel/plugins/`): a `HawkiPlugin` registers stores, resource/config schemas, modules, routes, and (for core plugins) migrations through lifecycle hooks, and is auto-discovered via `import.meta.glob('$lib/plugins/**/*.plugin.ts')`. All existing first-party frontend features were consolidated into a single `core` plugin at `resources/js/plugins/core/`. Third-party/runtime-installed plugins are not supported yet.
- Added a **feature module system** (`resources/js/kernel/modules/`): a `HawkiModule` bundles a localizable title/description/icon, namespaced routes, and an optional sidebar component behind one registered name (`${plugin}:${module}`). The chat feature is now registered as the `core:chat` module.
- The client-side **router is now actually wired up** (previously inert scaffolding, see `_documentation/600-Frontend/600-Advanced/200-Routing.md`): `RouterView`/`RouteView` resolve `universal-router` routes through pluggable strategies (`path`, `hash`, `transient`). Currently only reachable via new placeholder-only preview routes (`/new`, `/new/{slug}`) added to `routes/web.php`, in preparation for the planned HAWKI v3.0.0 single-page-app rewrite — the main app UI is unaffected.
- Added a `Shell` component + `ShellExtension` that mounts the Svelte app shell as soon as the DOM is ready (showing a loading indicator) while the rest of the kernel finishes booting in the background.
- New **hook-based access pattern** for components — `useApp()`, `useConfig()`, `useConnection()`, `useStore()`, `useTranslator()`, `useApi()` (`resources/js/app/hooks/`) — replacing direct imports of global singletons such as the old `utils/translator.ts` `__()` export and `components/app/AppContext.svelte.ts`.
- Large-scale reorganization of `resources/js/`: `data/`, `stores/`, `schemas/`, `encryption/`, and `oldUi/` moved under the new `kernel/`, `plugins/core/`, and `legacy/` directory structure; chat composer components moved to `resources/js/plugins/core/modules/chat/`; composer state "aspects" renamed to "slices" (`AttachmentSlice`, `GuardSlice`, `ModeSlice`, `ModelParameterSlice`, `ModelSlice`, `ModelUsageSlice`, `ToolSlice`).
- Added `universal-router` as a new frontend dependency and a `$plugins` path alias (Vite + `tsconfig.json`); `tsconfig.json` now uses `moduleResolution: "bundler"` and `skipLibCheck: true`; the `check` npm script now runs `svelte-check` with an explicit config and a larger Node heap (`--max-old-space-size=8192`) to avoid out-of-memory crashes during type checking, and a standalone `tsc` script was added.
- Expanded JSDoc usage examples and rationale comments across the UI primitive component library (`Badge`, `Button`, `Dialog`, `DropdownMenu*`, `Citation*`, `ToastContext`, and others). `Button` also gained an `accent` variant and automatic icon-only sizing when only `iconLeft`/`iconRight` are given without children; the `lg` and standalone `icon` size options were removed.
- Added new architecture documentation: "The App & Kernel", "Writing an Extension", "Writing a Plugin", and "Routing" (marked not-yet-fully-active), plus updates to the Contributing, Stores, Translations, and Old UI Integration docs.
- Reworked the route middleware contract to a PHP-style pattern: a middleware now receives a `next()` callback and either returns a `RouteResultBody` to take over rendering, calls `await next()` to pass through, or returns nothing to deny access (`RouteMiddleware` in `RouteRegistrar.ts`, `buildMiddlewareStack.ts`). `ResolvedRouteRenderable` was renamed to `RouteResultBody` to match.
- Plugin routes are now automatically namespaced under `/plugins/<slug>` (core plugin routes remain unprefixed at the root), matching the existing module route namespacing (`getPluginRoutePrefix` in `routeInflection.ts`).
- Added a `canHandlePath()` hook to the `RoutingStrategy` interface (default: paths starting with `/`) so strategies can distinguish routable paths from local hrefs such as hash anchors or query-only links; `RouterHandle` and `Link.svelte` use it to decide whether to intercept a click or let the browser handle it.
- `RoutingExtension`'s public `app.router` is now typed as `RouterHandle` instead of the raw `universal-router` instance, removing an internal-only escape hatch from the public API. An `app.__router` field (marked `@internal`) still exposes the full router for `Shell.svelte`'s bootstrap.

### Deprecation

[//]: # (- List of features or functionalities that have been deprecated in this version.)
