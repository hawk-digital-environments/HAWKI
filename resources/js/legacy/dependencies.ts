/**
 * # Lazy third-party dependency loader for the old UI
 *
 * **Part of the transitional `legacy/` bridge.** This module only exists to
 * serve the old vanilla-JS UI in `public/js/*.js` while HAWKI is migrated to a
 * single-page Svelte 5 app; it is expected to disappear together with those
 * scripts.
 *
 * WHAT: a small registry of async loader functions for heavy third-party
 * libraries, plus {@link dependencyLoader}, which resolves one by name and
 * caches the resulting promise.
 *
 * WHY it exists: the legacy scripts are plain `<script>` files with no module
 * system — they cannot `import()` anything themselves and would otherwise need
 * their libraries bundled into the main chunk (or loaded from a CDN). Since
 * most of those libraries are only needed for rarely-used features (PDF
 * preview, Word/PDF export, image cropping, websockets), bundling them all
 * eagerly would bloat first paint for everyone. Instead
 * {@link provideLegacyGlobals} publishes this function as
 * `window.hawkiDependencyLoader`, and the legacy code awaits the library right
 * before it needs it. Vite sees the static `import()` calls below and emits a
 * separate chunk per library.
 *
 * NEW CODE SHOULD NOT USE THIS. In a Svelte module just write a normal
 * `import` (or a plain `await import()` for code-splitting) — you have a
 * module system, so you do not need this indirection.
 *
 * @example
 * // From legacy JS (see e.g. `public/js/export.js`, `public/js/file_manager.js`):
 * const jsPDF = await window.hawkiDependencyLoader('jsPdf');
 * const echo = await window.hawkiDependencyLoader('echo');
 */
import type Pusher from 'pusher-js';
import {getHawkiApp} from '$lib/legacy/legacy.js';

/**
 * The registry of loadable dependencies. Each entry is an async factory that
 * imports (and, where the legacy code requires it, globally installs and
 * configures) one library. Add an entry here to make a new library available
 * to the legacy scripts; the key becomes the name passed to
 * {@link dependencyLoader}.
 */
const dependencies = {
    /**
     * Laravel Echo bound to the Reverb broadcaster, used by the group-chat
     * code for realtime message updates (`public/js/groupchat_functions.js`).
     *
     * Reads its connection settings from the `hawki-core` config
     * (`transfer.websocket`) and installs `pusher-js` as `window.Pusher`,
     * because Echo's Pusher/Reverb connector looks the constructor up there.
     *
     * @throws Error when `transfer.websocket` is missing from the config,
     *         i.e. websockets are not configured for this installation.
     */
    echo: async () => {
        const [p, e] = await Promise.all([
            import('pusher-js'),
            import('laravel-echo')
        ]);
        const pusher = p.default;
        const Echo = e.default;

        const config = getHawkiApp().config.get('hawki-core');
        if (!config.transfer?.websocket) {
            throw new Error('WebSocket configuration is missing in hawki-core config.');
        }
        const wsConfig = config.transfer.websocket;

        window.Pusher = pusher;

        return (new Echo({
            broadcaster: 'reverb',
            key: wsConfig.key || '',
            wsHost: wsConfig.host,
            wsPath: wsConfig.path || undefined,
            wsPort: wsConfig.port,
            wssPort: wsConfig.port,
            forceTLS: wsConfig.forceTls,
            enabledTransports: ['ws', 'wss']
        }));
    },
    /** Cropper.js — used by the avatar/image cropping UI (`public/js/image-selector.js`). */
    cropperJs: async () => (await import('cropperjs')).default,
    /** jsPDF — used to render a conversation as a PDF export (`public/js/export.js`). */
    jsPdf: async () => (await import('jspdf')).default,
    /**
     * pdf.js — used to preview PDF attachments (`public/js/file_manager.js`).
     *
     * Does three extra things beyond the import, all required by the legacy
     * viewer code: it publishes the library as `window.pdfjsLib`, pulls in the
     * bundled `pdf_viewer` UI module, and points `GlobalWorkerOptions.workerSrc`
     * at the worker asset URL emitted by Vite (`?url` import).
     */
    pdfJsLib: async () => {
        const libModule = await import('pdfjs-dist');
        window.pdfjsLib = libModule;
        // @ts-ignore
        await import('pdfjs-dist/web/pdf_viewer');
        const pdfWorker = (await import('pdfjs-dist/build/pdf.worker.min?url')).default;
        libModule.GlobalWorkerOptions.workerSrc = pdfWorker;
        return libModule;
    },
    /** `docx` — used to build Word exports of a conversation (`public/js/export.js`). */
    docx: async () => await import('docx'),
    /** `docx-preview` — used to render Word attachments inline (`public/js/file_manager.js`). */
    docxPreview: async () => await import('docx-preview')
};

/** Union of the valid dependency names accepted by {@link dependencyLoader}. */
type DependencyName = keyof typeof dependencies;

/**
 * Globals installed by some of the loaders above. These are not for new code —
 * they only exist because the corresponding libraries/legacy scripts expect to
 * find themselves on `window`.
 */
declare global {
    interface Window {
        /** Set by the `echo` loader; Echo's Reverb connector resolves the Pusher constructor from here. */
        Pusher?: typeof Pusher;
        /** Set by the `pdfJsLib` loader; the legacy PDF viewer code reads the library from here. */
        pdfjsLib?: typeof import('pdfjs-dist');
    }
}

/**
 * Cache of in-flight/settled loader promises, keyed by dependency name. Storing
 * the *promise* (not the resolved module) means concurrent callers share a
 * single import instead of racing to load the library twice.
 */
const dependencyPromises = new Map<DependencyName, Promise<any>>();

/**
 * This function loads a dependency by name and returns a promise that resolves to the loaded module.
 * It caches the promise for each dependency, so subsequent calls with the same name will return the same promise.
 * This is used to avoid loading all dependencies in the main chunk, even if the legacy code doesn't use them.
 * Instead, dependencies are loaded on demand when the legacy code calls this function.
 *
 * Published to the legacy scripts as `window.hawkiDependencyLoader` by
 * {@link provideLegacyGlobals}. New Svelte/TS code should use a plain
 * `import` instead — see the module doc-block.
 *
 * Note that a *failed* load is cached as well: the rejected promise stays in
 * the cache, so a later retry will reject immediately with the same error
 * rather than attempting the import again.
 *
 * @param name Key of an entry in the `dependencies` registry above, e.g. `'jsPdf'`.
 * @returns The module/value produced by that entry's loader.
 * @throws Error synchronously when `name` is not a registered dependency.
 *
 * @example
 * const cropper = await dependencyLoader('cropperJs');
 */
export function dependencyLoader<TName extends DependencyName>(name: TName): Promise<typeof dependencies[TName] extends () => Promise<infer T> ? T : never> {
    if (!dependencyPromises.has(name)) {
        const loader = dependencies[name];
        if (!loader) {
            throw new Error(`Dependency loader for "${name}" not found.`);
        }
        dependencyPromises.set(name, loader());
    }
    return dependencyPromises.get(name)!;
}
