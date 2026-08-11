# v%%VERSION%%

### What's New

- Update `openai_models.php` to include GPT-5.6 Luna, Terra and Sol.

### Quality of Life

[//]: # (- Improvements and enhancements that improve the user experience.)

### Bugfix

- Temperature und Top-P sliders are disabled, if the model doesn't support them.

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

### Deprecation

[//]: # (- List of features or functionalities that have been deprecated in this version.)
