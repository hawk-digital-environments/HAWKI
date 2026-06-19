/**
 * App startup sequencing — ensures async initialization tasks (e.g. loading
 * config from the API) finish before any UI code that depends on them runs.
 *
 * Typical flow:
 * 1. Feature modules call {@link runBeforeReady} to register their async setup.
 * 2. The app entry-point (or Svelte root component) calls {@link waitUntilReady}
 *    to defer mounting until everything is initialized.
 */

// Run at most this many same-priority tasks concurrently. Keeps network
// requests from all firing at once while still parallelizing a few at a time.
const BEFORE_READY_BATCH_SIZE = 3;

const readyCallbacks: Array<() => void> = [];
const beforeReadyCallbacks: Array<{ callback: () => Promise<void>; priority: number }> = [];

let isReady = false;
let isInitialized = false;
let bootstrapCompleted = false;

async function runInBatches(callbacks: Array<() => Promise<void>>): Promise<void> {
    for (let i = 0; i < callbacks.length; i += BEFORE_READY_BATCH_SIZE) {
        await Promise.all(callbacks.slice(i, i + BEFORE_READY_BATCH_SIZE).map(cb => cb()));
    }
}

/**
 * Runs all registered before-ready tasks in priority order, then flushes
 * the ready-callback queue. The `isInitialized` guard prevents this from
 * being triggered multiple times if several `waitUntilReady` calls arrive
 * before the first one completes.
 */
async function triggerReady() {
    if (!bootstrapCompleted) {
        // We delay the initialization until the bootstrap is completed, to allow all before-ready tasks to be registered first.
        // This also allows the app entry point to call waitUntilReady() before the bootstrap, if needed.
        return;
    }
    if (isInitialized) {
        // If the initialization is already in progress or done, we don't want to trigger it again
        return;
    }
    isInitialized = true;

    const sorted = [...beforeReadyCallbacks].sort((a, b) => a.priority - b.priority);
    const priorities = [...new Set(sorted.map(e => e.priority))];

    for (const priority of priorities) {
        await runInBatches(sorted.filter(e => e.priority === priority).map(e => e.callback));
    }

    isReady = true;
    while (readyCallbacks.length > 0) {
        readyCallbacks.shift()!();
    }
}

/**
 * Marks the bootstrap process as completed, allowing the initialization sequence to start.
 * This is called from the bootstrap once it's done with its setup, to kick off the rest of the initialization.
 * @internal
 */
export function markBootstrapCompleted() {
    bootstrapCompleted = true;
    if (readyCallbacks.length > 0) {
        return triggerReady();
    }
    return Promise.resolve();
}

/**
 * Registers an async task that must complete before the app is considered
 * ready. Call this in module-level code (not inside components) so it is
 * registered before the first {@link waitUntilReady} call.
 *
 * Lower `priority` numbers run first. Tasks with the same priority run
 * concurrently (in batches of {@link BEFORE_READY_BATCH_SIZE}).
 *
 * @example
 * // Load config before anything else (priority 1 runs before the default 10)
 * runBeforeReady(() => loadConfig(), 1);
 */
export function runBeforeReady(callback: () => Promise<void>, priority: number = 10) {
    beforeReadyCallbacks.push({callback, priority});
}

/**
 * Runs `callback` once all before-ready tasks have completed. If the app is
 * already ready when this is called, the callback fires synchronously.
 *
 * Calling this also kicks off the initialization sequence if it hasn't
 * started yet, so it is safe to call from multiple places.
 *
 * @example
 * waitUntilReady(() => mountApp());
 */
export function waitUntilReady(callback: () => void) {
    if (isReady) {
        callback();
        return;
    }
    readyCallbacks.push(callback);
    triggerReady();
}
