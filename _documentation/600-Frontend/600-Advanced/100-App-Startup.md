# App Startup & Boot Sequence

The HAWKI frontend does not run feature code immediately on page load. `app.ts` assembles a `HawkiApp` from an ordered list of extensions via `createApp(bootstrapper, […])`, then calls `bootstrapper.run()` once. Each extension registers its own startup work into the `Bootstrapper`'s ordered stages during `init()`/`ready()`. Each stage fully resolves before the next one begins; within a stage, handlers run concurrently (up to 3 at a time). This guarantees that foundational infrastructure — connection, config — is always available before feature code runs.

See [The App & Kernel](110-App-and-Kernel.md) for how the extensions are assembled; this page covers the boot stages themselves.

## Overview

The boot sequence follows this order:

```
preparation → migration → early → main → late → finalization
```

`bootstrapper.run()` is idempotent: subsequent calls return the same promise as the first.

## Boot Stages

| Stage | What runs here |
|---|---|
| `preparation` | `ClientExtension` fetches the connection; `ConfigurationExtension` fetches the config — both register `onPreparationStage`, so they run concurrently. Everything else depends on both. |
| `migration` | *(currently unused — reserved for schema or storage migrations)*. Frontend migrations run on demand via `app.migration.apply(runType)` after login/passkey, not on a boot stage. |
| `early` | *(currently unused — reserved for services that `main`-stage work depends on)* |
| `main` | `StoreExtension` calls `loadData(app)` on every store that implements it; `LocalizationExtension` loads the active locale's translation labels — all concurrent. Plugins may add more `main`-stage work from their `boot()` hook. |
| `late` | `app.ts` injects the `LegacySharedContent` snippet into the DOM. |
| `finalization` | Plugin `ready()` hooks run (`onStageReached`); `app.ts` waits for `DOMContentLoaded`; the core plugin defines the `<svelte-snippet>` custom element (`onStagePassed`). |

The `migration` and `early` stages are intentionally empty in the current codebase. They exist as reserved slots for future work that must run after `preparation` but before `main`.

## Registering Work in a Stage

Each stage exposes three registration points that control precisely when a handler runs relative to that stage. Extensions register these from their `init()`/`ready()`; you rarely call them outside an extension (see [Writing an Extension](120-Writing-an-Extension.md)).

### `onStageReached(stage, handler)`

Runs **before** the stage starts, serially. Use this to set up preconditions that the stage's concurrent handlers depend on. All `onStageReached` handlers for a stage complete before any `onStage` handlers begin.

```ts
import {bootstrapper} from '$lib/kernel/Bootstrapper.js';

bootstrapper.onStageReached('main', async (bootstrap) => {
    // Runs before any 'main' stage handlers start.
    await ensurePrecondition();
});
```

### `onStage(stage, handler)`

Runs **during** the stage, concurrently with other handlers registered for the same stage (up to the concurrency limit of 3). This is where most feature setup goes. Returns a cleanup function that deregisters the handler.

Each stage also has a named shorthand method:

| Shorthand | Equivalent |
|---|---|
| `onPreparationStage(fn)` | `onStage('preparation', fn)` |
| `onMigrationStage(fn)` | `onStage('migration', fn)` |
| `onEarlyStage(fn)` | `onStage('early', fn)` |
| `onMainStage(fn)` | `onStage('main', fn)` |
| `onLateStage(fn)` | `onStage('late', fn)` |
| `onFinalizationStage(fn)` | `onStage('finalization', fn)` |

```ts
import {bootstrapper} from '$lib/kernel/Bootstrapper.js';

bootstrapper.onMainStage(async (bootstrap) => {
    await loadMyFeature();
});
```

### `onStagePassed(stage, handler)`

Runs **after** the stage completes, serially. Use this to react to a stage finishing without blocking the next stage from starting.

```ts
bootstrapper.onStagePassed('main', async (bootstrap) => {
    // All 'main' handlers have resolved.
    reportReadinessMetric();
});
```

### Late Registration

If a handler is registered after its target stage (and timing slot) has already passed, it is called immediately and a console warning is emitted:

```
Trying to register a bootstrap handler for stage main and timing before, but that timing has already passed. Running handler immediately.
```

Late registration is never silently dropped.

## `Bootstrapper` API Reference

| Method | When it runs | Execution |
|---|---|---|
| `onStageReached(stage, fn)` | Before stage starts | Serial |
| `onStage(stage, fn)` | During stage | Concurrent (max 3) |
| `on{Stage}Stage(fn)` | During the named stage | Concurrent (max 3) |
| `onStagePassed(stage, fn)` | After stage completes | Serial |
| `run()` | — | Starts the full sequence; idempotent |
| `currentStage` | — | Read-only getter for the active stage name |

The `Bootstrapper` is constructed in `app.ts` and passed to each extension's `init()`/`ready()`. Legacy code receives it through the `window.waitUntilBootstrap(cb)` callback (see [Old UI Integration](300-Old-Ui.md)) — it is no longer exposed as `window.hawkiBootstrap`.

```ts
import {bootstrapper} from '$lib/kernel/Bootstrapper.js';
```

## Stage Concurrency

Within each stage, handlers registered via `onStage` (and the named shorthands) run concurrently with a cap of 3 simultaneous handlers. As one completes, the next queued handler starts — a sliding window, not fixed batches. The stage does not advance until all handlers have resolved.

## Lazy Dependencies

`legacy/dependencies.ts` exports a `dependencyLoader(name)` function that loads heavy third-party libraries on demand rather than bundling them into the main chunk. It is published to the legacy scripts as `window.hawkiDependencyLoader` by `provideLegacyGlobals()`.

:::warning[Legacy only]
`dependencyLoader` exists solely to serve the old vanilla-JS UI, which has no module system. **New Svelte/TS code must not use it** — write a normal `import` (or a plain `await import()` for code-splitting) instead.
:::

```ts
import {dependencyLoader} from '$lib/legacy/dependencies.js';

const echo = await dependencyLoader('echo');
// echo is a fully configured Laravel Echo instance connected via Reverb/Pusher
```

### Registered Dependencies

| Name | Package(s) loaded | Notes |
|---|---|---|
| `echo` | `pusher-js`, `laravel-echo` | Configures a Laravel Echo instance using `hawki-core` WebSocket config; sets `window.Pusher` |
| `cropperJs` | `cropperjs` | Image cropping |
| `jsPdf` | `jspdf` | Client-side PDF generation |
| `pdfJsLib` | `pdfjs-dist`, `pdfjs-dist/web/pdf_viewer` | PDF rendering; sets `window.pdfjsLib` and configures the worker URL |
| `docx` | `docx` | DOCX file creation |
| `docxPreview` | `docx-preview` | DOCX file preview rendering |

The promise cache (`dependencyPromises`) is module-level, so each dependency is instantiated at most once per page load even if `dependencyLoader` is called from multiple components. A failed load is cached too — a later retry rejects immediately rather than re-importing.
