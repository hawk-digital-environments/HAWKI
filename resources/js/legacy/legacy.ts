/**
 * # legacy.ts — the `window` seam between the old UI and the new Svelte app
 *
 * **Part of the transitional `legacy/` bridge.** Every export in this file is
 * `@deprecated` on purpose: it exists only for as long as HAWKI still ships the
 * old Blade + vanilla-JS UI (`public/js/*.js`, `resources/views/**`) alongside
 * the new Svelte 5 app, and is meant to be deleted, not extended.
 *
 * WHAT it does, in three parts:
 * 1. **Globals** — {@link provideLegacyGlobals} copies a hand-picked set of
 *    kernel functions, bridges and stores onto `window` so the legacy scripts
 *    can reach them (see the `declare global` block below for the full list).
 * 2. **Startup queues** — {@link runLegacyWaitUntilBootstrapQueue} and
 *    {@link runLegacyWaitUntilReadyQueue} drain the callbacks that inline
 *    scripts registered via `window.waitUntilBootstrap()` /
 *    `window.waitUntilReady()` before the bundle even loaded.
 * 3. **App accessor** — {@link setHawkiApp}/{@link getHawkiApp} hold the
 *    {@link HawkiApp} instance in a module-level singleton, so non-Svelte code
 *    (and other legacy modules) can reach the app without dependency injection.
 *
 * WHY it exists: the legacy scripts are plain `<script>` files with no module
 * system. `window` is the only namespace both sides can see, so this file is
 * the deliberately narrow, explicitly-typed doorway through it — instead of
 * globals being sprinkled ad-hoc across the codebase.
 *
 * NEW CODE MUST NOT USE ANY OF THIS. Import the real modules directly
 * (`$lib/kernel/...`), use `useApp()`/`useConfig()`/`useStore()` from
 * `$lib/app/hooks/*` inside components, and never read from `window`.
 *
 * @see EarlyFrontendBridge (`app/Services/Frontend/View/EarlyFrontendBridge.php`)
 *      — the Blade component that installs the `waitUntilReady`/`waitUntilBootstrap`
 *      stubs and their queue arrays before this bundle is parsed.
 * @see resources/js/app.ts — calls all three parts in the right order.
 */
import {getAuthenticatedConnection, getConnection, getConnectionWithUserInfo} from '$lib/kernel/client/helpers.js';
import {__} from '$lib/kernel/localization/helpers.js';
import {oldUiBridge} from '$lib/legacy/OldUiBridge.svelte.js';
import {applyMigrations} from '$lib/kernel/migrations/helpers.js';
import {getFileIconSvg} from '$lib/utils/fileIconSvg.js';
import {oldUiMessageHistory} from '$lib/legacy/OldUiMessageHistory.svelte.js';
import {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import {dependencyLoader} from '$lib/legacy/dependencies.js';
import {buildStorageFileUrl} from '$lib/utils/storageFileProxy.js';
import {getConfig} from '$lib/kernel/config/helpers.js';
import type {WellKnownSystemModelType} from '$plugins/core/schemas/resources/system-models.schema.js';
import type {WellKnownSystemPromptType} from '$plugins/core/schemas/resources/system-prompts.schema.js';
import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {KeychainStore} from '$plugins/core/stores/KeychainStore.svelte.js';

// Augment the global Window interface to include our globals, so that they can be accessed without TypeScript errors.
// WARNING: This is only here for legacy support! Do not use global variables in new code!
declare global {
    interface Window {
        /**
         * Queue of `waitUntilBootstrap()` callbacks collected before this bundle
         * loaded. Created by the `<x-early-frontend-bridge />` Blade component;
         * drained by {@link runLegacyWaitUntilBootstrapQueue}.
         */
        hawkiEarlyWaitUntilBootstrapQueue: Array<(bootstrapper: Bootstrapper) => Promise<void>>;
        /**
         * Queue of `waitUntilReady()` callbacks collected before this bundle
         * loaded. Created by the `<x-early-frontend-bridge />` Blade component;
         * drained by {@link runLegacyWaitUntilReadyQueue}.
         */
        hawkiEarlyWaitUntilReadyQueue: Array<() => Promise<void>>;
        /** `true` once the bootstrapper has finished; the early stub uses it to run late callbacks immediately (with a warning). */
        hawkiIsReady: boolean;
        /** {@link dependencyLoader} — lazy loader for heavy third-party libs, see `$lib/legacy/dependencies.js`. */
        hawkiDependencyLoader: typeof dependencyLoader;
        /**
         * Registers a callback to run once the {@link Bootstrapper} exists but
         * before it has run. Declared here for typing only — the implementation
         * is the stub installed by `<x-early-frontend-bridge />`.
         */
        waitUntilBootstrap: (cb: (bootstrapper: Bootstrapper) => Promise<void> | void) => void;
        /**
         * Registers a callback to run after the app has fully booted. This is the
         * main entry hook the legacy scripts and Blade templates use.
         * Declared here for typing only — see `waitUntilBootstrap` above.
         */
        waitUntilReady: (cb: () => Promise<void> | void) => void;
        /** The {@link OldUiBridge} singleton — the event/state channel between both UIs. */
        oldUiBridge: typeof oldUiBridge;
        /** The {@link OldUiMessageHistory} singleton — the shared conversation/message state. */
        oldUiMessageHistory: typeof oldUiMessageHistory;
        /** Kernel HTTP client factory for anonymous requests. */
        getConnection: typeof getConnection;
        /** Kernel HTTP client factory for authenticated requests. */
        getAuthenticatedConnection: typeof getAuthenticatedConnection;
        /** Kernel HTTP client factory for requests that carry the current user's info. */
        getConnectionWithUserInfo: typeof getConnectionWithUserInfo;
        /** Reads a validated config bundle (defaults to `hawki-core`). */
        getConfig: typeof getConfig;
        /** Runs the pending keychain/encryption migrations; called from the legacy handshake/passkey flows. */
        applyMigrations: typeof applyMigrations;
        /** Translation helper (`__('some.label')`), mirroring the Laravel-side API. */
        __: typeof __;
        /** Builds a proxied URL for a stored file (attachments, avatars, ...). */
        buildStorageFileUrl: typeof buildStorageFileUrl;
        /** The current `KeychainStore` instance. Defined as a getter so it always resolves to the live store, see {@link provideLegacyGlobals}. */
        userKeychain: KeychainStore;
        /** All AI models known to the `ai-models` store. */
        getAiModels: () => AiModel[];
        /** Looks up a single AI model by its id; `null` when unknown. */
        getAiModel: (id: string | number) => AiModel | null;
        /** Resolves a well-known system model slot (e.g. summarization) to a concrete model; `null` when not configured. */
        getSystemModel: (modelType: WellKnownSystemModelType | string) => AiModel | null;
        /** Returns the text of a well-known system prompt; `null` when not configured. */
        getSystemPrompt: (promptName: WellKnownSystemPromptType) => string | null;
        /** Returns an inline SVG file-type icon for a mime type / file name. */
        getFileIconSvg: typeof getFileIconSvg;
    }
}

/**
 * Publishes the legacy `window` API listed in the `declare global` block above.
 *
 * Must be called **before** the app is created (see `resources/js/app.ts`),
 * because legacy inline scripts may already be queueing work by then. It is
 * safe to call this early even though most of the globals reach into the
 * {@link HawkiApp}: every app-dependent global is a *closure* or a getter that
 * resolves `getHawkiApp()` lazily at call time, not at definition time.
 *
 * Nothing here should be added to without a very good reason — every new
 * global is another thing to remove before the migration can finish.
 *
 * @deprecated Will be removed as soon as all the old js has been refactored
 */
export function provideLegacyGlobals() {
    /** Lazily resolves the `ai-models` store from the app; kept local so the globals below stay one-liners. */
    function getAiModelStore() {
        return getHawkiApp().stores.get('ai-models');
    }

    // Propagate some important functions and objects to the global scope, so the legacy code can access them.
    window.getConnection = getConnection;
    window.getAuthenticatedConnection = getAuthenticatedConnection;
    window.getConnectionWithUserInfo = getConnectionWithUserInfo;
    window.getConfig = getConfig;
    window.__ = __;
    window.applyMigrations = applyMigrations;
    window.oldUiBridge = oldUiBridge;
    window.oldUiMessageHistory = oldUiMessageHistory;
    window.buildStorageFileUrl = buildStorageFileUrl;
    window.hawkiDependencyLoader = dependencyLoader;
    window.getAiModels = () => getAiModelStore().models;
    window.getAiModel = (id: string | number) => getAiModelStore().getOneById(id);
    window.getSystemModel = (modelType: WellKnownSystemModelType | string) => getAiModelStore().getSystemModelByType(modelType);
    window.getSystemPrompt = (promptType: WellKnownSystemPromptType) => getHawkiApp().stores.get('system-prompts').promptsByType.get(promptType)?.prompt ?? null;
    window.getFileIconSvg = getFileIconSvg;

    // Assign userKeychain property using a getter to ensure it always returns the latest instance of KeychainStore
    Object.defineProperty(window, 'userKeychain', {
        get: () => {
            return getHawkiApp().stores.get('keychain');
        }
    });
}

/**
 * Runs every callback that was queued via `window.waitUntilBootstrap()` before
 * the bundle loaded, handing each one the {@link Bootstrapper}.
 *
 * Callbacks run **sequentially**, in registration order, and are awaited — so a
 * slow callback delays the whole boot. They run *before* `bootstrapper.run()`,
 * which is the point: this is the last chance for legacy inline scripts (e.g.
 * the handshake page, see `resources/views/partials/gateway/handshake.blade.php`)
 * to hook into a bootstrap stage.
 *
 * @deprecated Will be removed as soon as all the old js has been refactored
 */
export async function runLegacyWaitUntilBootstrapQueue(bootstrapper: Bootstrapper) {
    if (Array.isArray(window.hawkiEarlyWaitUntilBootstrapQueue)) {
        for (const cb of window.hawkiEarlyWaitUntilBootstrapQueue) {
            await cb(bootstrapper);
        }
    }
}

/**
 * Flips `window.hawkiIsReady` and runs every callback queued via
 * `window.waitUntilReady()`, sequentially and awaited, in registration order.
 *
 * Called last in `resources/js/app.ts`, after `bootstrapper.run()` has
 * completed — so callbacks can assume the whole app (config, stores,
 * localization, snippets, the `<svelte-snippet>` element) is available. Setting
 * `hawkiIsReady` first makes the early stub from `<x-early-frontend-bridge />`
 * invoke any *later* registration immediately instead of dropping it into a
 * queue nobody drains again.
 *
 * @deprecated Will be removed as soon as all the old js has been refactored
 */
export async function runLegacyWaitUntilReadyQueue() {
    window.hawkiIsReady = true;
    if (Array.isArray(window.hawkiEarlyWaitUntilReadyQueue)) {
        for (const cb of window.hawkiEarlyWaitUntilReadyQueue) {
            await cb();
        }
    }
}

/** The single {@link HawkiApp} instance, set once by {@link setHawkiApp} during startup. */
let hawkiAppInstance: HawkiApp | null = null;

/**
 * Stores the {@link HawkiApp} instance so {@link getHawkiApp} can hand it out
 * later. Called exactly once, from `resources/js/app.ts`, with the result of
 * `createApp()`.
 *
 * @returns the same app instance, so it can be used inline.
 * @throws Error when called a second time — a duplicated bootstrap script would
 *         otherwise silently swap the app out from under everything that
 *         already holds a reference.
 * @deprecated Will be removed as soon as all the old js has been refactored
 */
export function setHawkiApp(app: HawkiApp) {
    if (hawkiAppInstance) {
        throw new Error('HawkiApp instance has already been set.');
    }
    hawkiAppInstance = app;
    return app;
}

/**
 * Returns the {@link HawkiApp} singleton.
 *
 * This is the escape hatch for code that runs *outside* a Svelte component and
 * therefore cannot use `useApp()` — e.g. the loaders in
 * `$lib/legacy/dependencies.js` or the closures in {@link provideLegacyGlobals}.
 * Inside components always prefer `useApp()` (`$lib/app/hooks/useApp.svelte.js`),
 * which goes through Svelte context and keeps the component testable.
 *
 * Because the app is only available after `createApp()` resolved, call this
 * *lazily* (inside the function that needs it), never at module top level.
 *
 * @throws Error when the app has not been assembled yet.
 * @deprecated Will be removed as soon as all the old js has been refactored
 */
export function getHawkiApp(): HawkiApp {
    if (!hawkiAppInstance) {
        throw new Error('HawkiApp instance has not been set yet.');
    }
    return hawkiAppInstance;
}
