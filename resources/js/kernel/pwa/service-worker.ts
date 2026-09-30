export {};

declare const self: ServiceWorkerGlobalScope;

const OFFLINE_CACHE_NAME = 'hawki-pwa-offline-v1';
const ASSET_CACHE_NAME = 'hawki-pwa-assets-v1';
const OFFLINE_PAGE = '/pwa/offline.html';
const OFFLINE_CACHE_PREFIX = 'hawki-pwa-offline-';
const ASSET_CACHE_PREFIX = 'hawki-pwa-assets-';
const MAX_ASSET_ENTRIES = 256;
const PWA_ICON_PATHS = new Set([
    '/pwa/icons/apple-touch-icon.png',
    '/pwa/icons/icon-192.png',
    '/pwa/icons/icon-512.png',
    '/pwa/icons/icon-maskable-512.png'
]);
const ASSET_TYPES: Record<string, string[]> = {
    js: ['application/javascript', 'text/javascript'],
    css: ['text/css'],
    woff: ['font/woff', 'application/font-woff'],
    woff2: ['font/woff2', 'application/font-woff2'],
    ttf: ['font/ttf', 'application/x-font-ttf'],
    otf: ['font/otf', 'application/font-opentype'],
    png: ['image/png'],
    jpg: ['image/jpeg'],
    jpeg: ['image/jpeg'],
    svg: ['image/svg+xml'],
    webp: ['image/webp'],
    gif: ['image/gif'],
    ico: ['image/x-icon', 'image/vnd.microsoft.icon'],
    wasm: ['application/wasm']
};

self.addEventListener('install', event => {
    event.waitUntil(cacheOfflinePage());
});

self.addEventListener('activate', event => {
    event.waitUntil(removeOldCaches());
});

self.addEventListener('fetch', event => {
    const {request} = event;
    const url = new URL(request.url);

    if (shouldIgnore(request, url)) {
        return;
    }

    if (request.mode === 'navigate' && (url.pathname === '/new' || url.pathname.startsWith('/new/'))) {
        event.respondWith(networkFirst(request));
        return;
    }

    if (request.mode !== 'navigate' && !url.search && PWA_ICON_PATHS.has(url.pathname)) {
        const work = networkFirstAsset(request, url);
        event.respondWith(work);
        event.waitUntil(work.catch(() => undefined));
        return;
    }

    if (request.mode !== 'navigate' && isHashedBuildAsset(url)) {
        const work = cacheFirstAsset(request, url);
        event.respondWith(work);
        event.waitUntil(work.catch(() => undefined));
    }
});

async function cacheOfflinePage(): Promise<void> {
    const response = await fetch(OFFLINE_PAGE, {credentials: 'omit', cache: 'reload'});

    if (!response.ok || response.redirected || !response.headers.get('content-type')?.toLowerCase().startsWith('text/html')) {
        throw new Error('The offline page must be a successful, direct HTML response.');
    }

    const cache = await caches.open(OFFLINE_CACHE_NAME);
    await cache.put(OFFLINE_PAGE, response.clone());
}

async function networkFirst(request: Request): Promise<Response> {
    try {
        return await fetch(request);
    } catch {
        const cache = await caches.open(OFFLINE_CACHE_NAME);
        return await cache.match(OFFLINE_PAGE) ?? Response.error();
    }
}

function shouldIgnore(request: Request, url: URL): boolean {
    return request.method !== 'GET' ||
        url.origin !== self.location.origin ||
        request.cache === 'no-store' ||
        request.headers.get('authorization') !== null ||
        request.headers.get('range') !== null ||
        (request.headers.get('cache-control')?.toLowerCase().includes('no-store') ?? false);
}

function isHashedBuildAsset(url: URL): boolean {
    if (!url.pathname.startsWith('/build/assets/') || url.search) {
        return false;
    }

    const extension = extensionOf(url.pathname);
    return extension !== null && new RegExp(`-[A-Za-z0-9_-]{8,}\\.${extension}$`, 'i').test(url.pathname);
}

async function cacheFirstAsset(request: Request, url: URL): Promise<Response> {
    const cache = await caches.open(ASSET_CACHE_NAME).catch(() => undefined);
    if (cache) {
        const cached = await cache.match(request).catch(() => undefined);
        if (cached) {
            return cached;
        }
    }

    const response = await fetchPublicAsset(request);
    if (cache) {
        await cacheAssetResponse(cache, request, url.pathname, response);
    }
    return response;
}

async function networkFirstAsset(request: Request, url: URL): Promise<Response> {
    try {
        const response = await fetchPublicAsset(request);
        const cache = await caches.open(ASSET_CACHE_NAME).catch(() => undefined);
        if (cache) {
            await cacheAssetResponse(cache, request, url.pathname, response);
        }
        return response;
    } catch (error) {
        const cache = await caches.open(ASSET_CACHE_NAME).catch(() => undefined);
        const cached = cache && await cache.match(request).catch(() => undefined);
        if (cached) {
            return cached;
        }
        throw error;
    }
}

function fetchPublicAsset(request: Request): Promise<Response> {
    return fetch(request, {credentials: 'omit'});
}

async function cacheAssetResponse(cache: Cache, request: Request, path: string, response: Response): Promise<void> {
    if (!canCacheAsset(path, response)) {
        return;
    }

    try {
        await cache.put(request, response.clone());
        await trimAssetCache(cache);
    } catch {
        // Cache pressure must not turn a successful static-resource request into a failure.
    }
}

function canCacheAsset(path: string, response: Response): boolean {
    const extension = extensionOf(path);
    const contentType = response.headers.get('content-type')?.split(';', 1)[0].toLowerCase() ?? '';
    const cacheControl = response.headers.get('cache-control')?.toLowerCase() ?? '';
    return extension !== null &&
        response.status === 200 &&
        !response.redirected &&
        ASSET_TYPES[extension].includes(contentType) &&
        !/(?:^|,)\s*(?:private|no-store|no-cache)\b/.test(cacheControl);
}

function extensionOf(path: string): string | null {
    const match = /\.([a-z0-9]+)$/i.exec(path);
    return match && Object.hasOwn(ASSET_TYPES, match[1].toLowerCase()) ? match[1].toLowerCase() : null;
}

async function trimAssetCache(cache: Cache): Promise<void> {
    const keys = await cache.keys();
    const excess = keys.length - MAX_ASSET_ENTRIES;
    if (excess > 0) {
        await Promise.all(keys.slice(0, excess).map(key => cache.delete(key)));
    }
}

async function removeOldCaches(): Promise<void> {
    const cacheNames = await caches.keys();
    await Promise.all(cacheNames
        .filter(name =>
            (name.startsWith(OFFLINE_CACHE_PREFIX) && name !== OFFLINE_CACHE_NAME) ||
            (name.startsWith(ASSET_CACHE_PREFIX) && name !== ASSET_CACHE_NAME)
        )
        .map(name => caches.delete(name)));
    await self.clients.claim();
}
