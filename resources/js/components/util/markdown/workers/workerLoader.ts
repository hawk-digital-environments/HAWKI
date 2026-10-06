import { createModuleWorker } from '$lib/utils/moduleWorker';

const loadedWorkers = new Map<string, Worker>();

/**
 * Returns the shared module worker for `url`, creating it on first use.
 * `Markdown.svelte` registers workers on every component mount (one per chat
 * message, reasoning step, citation preview), so without this cache each
 * mount would spawn its own worker for the same script.
 * @see resources/js/utils/moduleWorker.ts for how the URL is loaded.
 */
export function loadWorker(url: string): Worker {
    const cached = loadedWorkers.get(url);
    if (cached) {
        return cached;
    }
    const worker = createModuleWorker(url, import.meta.url);
    loadedWorkers.set(url, worker);
    return worker;
}
