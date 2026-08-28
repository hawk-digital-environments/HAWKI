/**
 * Starts a module Web Worker from a script URL that may live on another
 * origin — in development Vite serves `resources/js` from its own port while
 * the page comes from the PHP server, and `new Worker(crossOriginUrl)` is a
 * security error. The workaround is a same-origin Blob module that merely
 * `import`s the real script; the browser allows cross-origin imports where
 * it forbids cross-origin worker scripts.
 *
 * Pass the URL Vite produces for `?worker&url` imports:
 *
 * @example
 * import searchWorkerUrl from './search.worker.ts?worker&url';
 * const worker = createCrossOriginWorker(searchWorkerUrl, import.meta.url);
 *
 * @see https://github.com/vitejs/vite/issues/13680
 */
export function createCrossOriginWorker(url: string, base: string): Worker {
    const blob = new Blob(
        [`import ${JSON.stringify(new URL(url, base))}`],
        {type: 'application/javascript'}
    );
    const objectUrl = URL.createObjectURL(blob);
    const worker = new Worker(objectUrl, {type: 'module'});
    worker.addEventListener('error', () => URL.revokeObjectURL(objectUrl));
    return worker;
}
