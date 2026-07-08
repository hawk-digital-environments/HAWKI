import type Pusher from 'pusher-js';
import {getConfig} from '$lib/data/config/config.js';

const dependencies = {
    echo: async () => {
        const [p, e] = await Promise.all([
            import('pusher-js'),
            import('laravel-echo')
        ]);
        const pusher = p.default;
        const Echo = e.default;

        const config = getConfig('hawki-core');
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
    cropperJs: async () => (await import('cropperjs')).default,
    jsPdf: async () => (await import('jspdf')).default,
    pdfJsLib: async () => {
        const libModule = await import('pdfjs-dist');
        window.pdfjsLib = libModule;
        // @ts-ignore
        await import('pdfjs-dist/web/pdf_viewer');
        const pdfWorker = (await import('pdfjs-dist/build/pdf.worker.min?url')).default;
        libModule.GlobalWorkerOptions.workerSrc = pdfWorker;
        return libModule;
    },
    docx: async () => await import('docx'),
    docxPreview: async () => await import('docx-preview')
};

type DependencyName = keyof typeof dependencies;

declare global {
    interface Window {
        Pusher?: typeof Pusher;
        pdfjsLib?: typeof import('pdfjs-dist');
    }
}

const dependencyPromises = new Map<DependencyName, Promise<any>>();

/**
 * This function loads a dependency by name and returns a promise that resolves to the loaded module.
 * It caches the promise for each dependency, so subsequent calls with the same name will return the same promise.
 * This is used to avoid loading all dependencies in the main chunk, even if the legacy code doesn't use them.
 * Instead, dependencies are loaded on demand when the legacy code calls this function.
 * @param name
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
