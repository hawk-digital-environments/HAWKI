/**
 * Browser-side persistence for the chat index (see `ChatIndexStore`): one
 * opaque text document, stored via the Storage API.
 *
 * The document goes into the Origin Private File System
 * (`navigator.storage.getDirectory()`) where the browser supports writing
 * to it from the main thread; Safari does not, so it falls back to a
 * single-record IndexedDB store. Either way the origin's storage is asked to
 * be marked persistent (`navigator.storage.persist()`), which stops the
 * browser from evicting the index under storage pressure — the caller only
 * learns whether that was granted.
 *
 * Callers never see which backend is in use; every function resolves to the
 * same result on both.
 */

const FILE_NAME = 'chat-index.json';
const DB_NAME = 'hawki-chat-index';
const DB_STORE = 'files';

/**
 * Asks the browser to keep this origin's storage. `true`/`false` mirror the
 * browser's answer; `null` when the Storage API is unavailable.
 */
export async function requestPersistentStorage(): Promise<boolean | null> {
    try {
        if (typeof navigator === 'undefined' || !navigator.storage?.persist) return null;
        if (await navigator.storage.persisted()) return true;
        return await navigator.storage.persist();
    } catch {
        return null;
    }
}

/** Whether any supported backend exists in this browser. */
export function isIndexStorageAvailable(): boolean {
    return supportsOpfs() || typeof indexedDB !== 'undefined';
}

/** The stored document, or `null` when none has been written yet. */
export async function readIndexFile(): Promise<string | null> {
    if (supportsOpfs()) {
        try {
            const root = await navigator.storage.getDirectory();
            const handle = await root.getFileHandle(FILE_NAME);
            return await (await handle.getFile()).text();
        } catch (error) {
            if (isNotFound(error)) return null;
            throw error;
        }
    }
    return idbRequest(store => store.get(FILE_NAME), 'readonly').then(value => typeof value === 'string' ? value : null);
}

/** Replaces the stored document. */
export async function writeIndexFile(content: string): Promise<void> {
    if (supportsOpfs()) {
        const root = await navigator.storage.getDirectory();
        const handle = await root.getFileHandle(FILE_NAME, {create: true});
        const writable = await handle.createWritable();
        try {
            await writable.write(content);
        } finally {
            await writable.close();
        }
        return;
    }
    await idbRequest(store => store.put(content, FILE_NAME), 'readwrite');
}

/** Deletes the stored document; a no-op when there is none. */
export async function removeIndexFile(): Promise<void> {
    if (supportsOpfs()) {
        try {
            const root = await navigator.storage.getDirectory();
            await root.removeEntry(FILE_NAME);
        } catch (error) {
            if (!isNotFound(error)) throw error;
        }
        return;
    }
    await idbRequest(store => store.delete(FILE_NAME), 'readwrite');
}

function supportsOpfs(): boolean {
    return typeof navigator !== 'undefined'
        && typeof navigator.storage?.getDirectory === 'function'
        && typeof FileSystemFileHandle !== 'undefined'
        && 'createWritable' in FileSystemFileHandle.prototype;
}

function isNotFound(error: unknown): boolean {
    return error instanceof DOMException && error.name === 'NotFoundError';
}

function idbRequest<T>(run: (store: IDBObjectStore) => IDBRequest<T>, mode: IDBTransactionMode): Promise<T> {
    return new Promise((resolve, reject) => {
        if (typeof indexedDB === 'undefined') {
            reject(new Error('No storage backend for the chat index is available in this browser.'));
            return;
        }
        const open = indexedDB.open(DB_NAME, 1);
        open.onupgradeneeded = () => open.result.createObjectStore(DB_STORE);
        open.onerror = () => reject(open.error);
        open.onsuccess = () => {
            const db = open.result;
            const request = run(db.transaction(DB_STORE, mode).objectStore(DB_STORE));
            request.onerror = () => {
                db.close();
                reject(request.error);
            };
            request.onsuccess = () => {
                db.close();
                resolve(request.result);
            };
        };
    });
}
