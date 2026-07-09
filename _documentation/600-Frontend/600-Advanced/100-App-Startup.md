# App Startup & Boot Sequence

The HAWKI frontend does not run feature code immediately on page load. Instead, `app.ts` registers all initialization work into an ordered sequence of async boot stages managed by the `Bootstrapper` singleton, then calls `bootstrapper.run()` once. Each stage fully resolves before the next one begins. Within a stage, handlers run concurrently (up to 3 at a time). This ordering guarantees that foundational infrastructure — connection, config — is always available before feature code runs.

## Overview

The boot sequence follows this order:

```
preparation → migration → early → main → late → finalization
```

`bootstrapper.run()` is idempotent: subsequent calls return the same promise as the first.

## Boot Stages

| Stage | What registers here |
|---|---|
| `preparation` | `loadConnection`, `loadConfig` — run concurrently; everything else depends on both |
| `migration` | *(currently unused — reserved for schema or storage migrations)* |
| `early` | *(currently unused — reserved for services that `main`-stage work depends on, e.g. auth, feature flags)* |
| `main` | `loadTranslationLabels`, `loadAiModels`, `loadAiToolsAndCapabilities`, `loadSystemPrompts` — all run concurrently |
| `late` | Creates `AppContext` and `ToastContext`; injects the `LegacySharedContent` Svelte snippet into the DOM |
| `finalization` | Waits for `DOMContentLoaded`; registers the Svelte snippet loader |

The `migration` and `early` stages are intentionally empty in the current codebase. They exist as reserved slots for future work that must run after `preparation` but before `main`.

## Registering Work in a Stage

Each stage exposes three registration points that control precisely when a handler runs relative to that stage.

### `onStageReached(stage, handler)`

Runs **before** the stage starts, serially. Use this to set up preconditions that the stage's concurrent handlers depend on. All `onStageReached` handlers for a stage complete before any `onStage` handlers begin.

```ts
import {bootstrapper} from '$lib/utils/Bootstrapper.js';

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
import {bootstrapper} from '$lib/utils/Bootstrapper.js';

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

The singleton is exported from `$lib/utils/Bootstrapper.js`:

```ts
import {bootstrapper} from '$lib/utils/Bootstrapper.js';
```

## Stage Concurrency

Within each stage, handlers registered via `onStage` (and the named shorthands) run concurrently with a cap of 3 simultaneous handlers. As one completes, the next queued handler starts — a sliding window, not fixed batches. The stage does not advance until all handlers have resolved.

## Lazy Dependencies

`dependencies.ts` exports a single `dependencyLoader(name)` function that loads large packages on demand rather than bundling them into the main chunk.

### Why It Exists

Several packages are large enough that including them in the initial bundle would add significant load time even when the feature using them is never opened in a given session. `dependencyLoader` defers the actual `import()` call until the first time a consumer requests the dependency. A shared promise cache ensures the package is only fetched once regardless of how many callers request it.

### How to Use It

```ts
import {dependencyLoader} from '$lib/dependencies.js';

const echo = await dependencyLoader('echo');
// echo is a fully configured Laravel Echo instance connected via Reverb/Pusher
```

The `echo` loader is the primary example of this pattern. It dynamically imports both `pusher-js` and `laravel-echo`, reads WebSocket configuration from the `hawki-core` config block, assigns `window.Pusher`, and returns a configured `Echo` instance. Nothing WebSocket-related loads until this function is first called.

### Registered Dependencies

| Name | Package(s) loaded | Notes |
|---|---|---|
| `echo` | `pusher-js`, `laravel-echo` | Configures a Laravel Echo instance using `hawki-core` WebSocket config; sets `window.Pusher` |
| `cropperJs` | `cropperjs` | Image cropping |
| `jsPdf` | `jspdf` | Client-side PDF generation |
| `pdfJsLib` | `pdfjs-dist`, `pdfjs-dist/web/pdf_viewer` | PDF rendering; sets `window.pdfjsLib` and configures the worker URL |
| `docx` | `docx` | DOCX file creation |
| `docxPreview` | `docx-preview` | DOCX file preview rendering |
| `md` | `markdown-it` | Pre-configured Markdown parser (HTML disabled, linkify off) |
| `hljs` | `highlight.js` | Syntax highlighting |
| `renderMathInElement` | `katex`, `katex/contrib/auto-render` | Math rendering; also imports the KaTeX CSS |

The promise cache (`dependencyPromises`) is module-level, so each dependency is instantiated at most once per page load even if `dependencyLoader` is called from multiple components.
