import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {test} from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../../public/sw.js', import.meta.url), 'utf8');
const origin = 'https://hawki.test';
const bundle = '/build/assets/app-abcdefgh.js';
const icon = '/pwa/icons/icon-192.png';
const assetCache = 'hawki-pwa-assets-v1';

function fixture({cacheFailure, initialEntries = []} = {}) {
    const handlers = new Map();
    const stores = new Map([[assetCache, new Map(initialEntries)]]);
    const calls = [];
    let network = () => new Response('bundle', {headers: {'content-type': 'text/javascript'}});
    const key = request => new URL(typeof request === 'string' ? request : request.url, origin).href;
    const caches = {
        async open(name) {
            if (cacheFailure === 'open') throw new Error('Cache unavailable');
            if (!stores.has(name)) stores.set(name, new Map());
            const entries = stores.get(name);
            return {
                async match(request) {
                    if (cacheFailure === 'match') throw new Error('Read failed');
                    return entries.get(key(request))?.clone();
                },
                async put(request, response) {
                    if (cacheFailure === 'put') throw new Error('Quota exceeded');
                    entries.set(key(request), response.clone());
                },
                async keys() { return [...entries.keys()].map(url => new Request(url)); },
                async delete(request) { return entries.delete(key(request)); }
            };
        },
        async keys() { return [...stores.keys()]; },
        async delete(name) { return stores.delete(name); }
    };
    vm.runInNewContext(source, {
        URL, Request, Response, Headers, caches, console,
        fetch: async (...args) => { calls.push(args); return network(...args); },
        self: {
            location: {origin}, clients: {claim: async () => {}},
            addEventListener: (name, handler) => handlers.set(name, handler)
        }
    });
    return {
        stores, calls,
        network: callback => { network = callback; },
        async request(path, init) {
            let result;
            const pending = [];
            const request = new Request(new URL(path, origin), init?.mode === 'navigate' ? {...init, mode: 'same-origin'} : init);
            if (init?.mode === 'navigate') Object.defineProperty(request, 'mode', {value: 'navigate'});
            handlers.get('fetch')({
                request,
                respondWith: promise => { result = promise; },
                waitUntil: promise => pending.push(promise)
            });
            const response = await result;
            await Promise.all(pending);
            return response;
        },
        async activate() {
            let pending;
            handlers.get('activate')({waitUntil: promise => { pending = promise; }});
            await pending;
        }
    };
}

test('reuses a downloaded bundle offline without another network request', async () => {
    const app = fixture();
    assert.equal(await (await app.request(bundle)).text(), 'bundle');
    assert.equal(app.calls[0][1].credentials, 'omit');
    app.network(() => { throw new TypeError('Offline'); });
    assert.equal(await (await app.request(bundle)).text(), 'bundle');
    assert.equal(app.calls.length, 1);
});

test('a new build hash fetches the new bundle', async () => {
    const app = fixture();
    await app.request(bundle);
    app.network(() => new Response('updated', {headers: {'content-type': 'text/javascript'}}));
    assert.equal(await (await app.request('/build/assets/app-ijklmnop.js')).text(), 'updated');
    assert.equal(app.calls.length, 2);
});

test('caches CSS, fonts, images, and WASM with matching content types', async () => {
    for (const [extension, type] of [['css', 'text/css'], ['woff2', 'font/woff2'], ['png', 'image/png'], ['svg', 'image/svg+xml'], ['wasm', 'application/wasm']]) {
        const app = fixture();
        app.network(() => new Response('asset', {headers: {'content-type': type}}));
        const path = `/build/assets/file-abcdefgh.${extension}`;
        await app.request(path);
        await app.request(path);
        assert.equal(app.calls.length, 1, extension);
    }
});

test('refreshes stable icon URLs online and falls back to the downloaded icon offline', async () => {
    const app = fixture();
    app.network(() => new Response('old', {headers: {'content-type': 'image/png'}}));
    await app.request(icon);
    app.network(() => new Response('new', {headers: {'content-type': 'image/png'}}));
    assert.equal(await (await app.request(icon)).text(), 'new');
    app.network(() => { throw new TypeError('Offline'); });
    assert.equal(await (await app.request(icon)).text(), 'new');
});

test('ignores private endpoints, mutable scripts, queries, other origins, and special requests', async () => {
    const app = fixture();
    for (const [path, init] of [
        ['/api/hawki/v1/messages'], ['/proxy/storage/file.png'], ['/js/functions.js'], ['/sw.js'], ['/manifest.json'],
        ['/build/assets/app.js'], [`${bundle}?token=secret`], [`${icon}?token=secret`], [`https://cdn.test${bundle}`],
        [bundle, {mode: 'navigate'}],
        [bundle, {method: 'POST'}], [bundle, {headers: {Authorization: 'Bearer test'}}],
        [bundle, {headers: {Range: 'bytes=0-10'}}], [bundle, {cache: 'no-store'}]
    ]) {
        assert.equal(await app.request(path, init), undefined, path);
    }
    assert.equal(app.calls.length, 0);
});

test('does not store errors, partial responses, HTML, or private/noncacheable responses', async () => {
    for (const init of [
        {status: 404, headers: {'content-type': 'text/javascript'}},
        {status: 206, headers: {'content-type': 'text/javascript'}},
        {headers: {'content-type': 'text/html'}},
        ...['private', 'no-store', 'no-cache'].map(value => ({headers: {'content-type': 'text/javascript', 'cache-control': value}}))
    ]) {
        const app = fixture();
        app.network(() => new Response('response', init));
        await app.request(bundle);
        await app.request(bundle);
        assert.equal(app.calls.length, 2, JSON.stringify(init));
        assert.equal(app.stores.get(assetCache).size, 0);
    }
});

test('does not store redirected asset responses', async () => {
    const app = fixture();
    app.network(() => {
        const response = new Response('redirected', {headers: {'content-type': 'text/javascript'}});
        Object.defineProperty(response, 'redirected', {value: true});
        return response;
    });
    await app.request(bundle);
    assert.equal(app.stores.get(assetCache).size, 0);
});

test('cache read and quota failures leave network responses usable', async () => {
    for (const cacheFailure of ['open', 'match', 'put']) {
        const app = fixture({cacheFailure});
        assert.equal(await (await app.request(bundle)).text(), 'bundle', cacheFailure);
    }
});

test('bounds the asset cache and evicts the oldest downloaded entry', async () => {
    const oldest = `${origin}/build/assets/old-abcdefgh.js`;
    const initialEntries = Array.from({length: 256}, (_, i) => [i === 0 ? oldest : `${origin}/build/assets/file${i}-abcdefgh.js`, new Response('old')]);
    const app = fixture({initialEntries});
    await app.request(bundle);
    const entries = app.stores.get(assetCache);
    assert.equal(entries.size, 256);
    assert.equal(entries.has(oldest), false);
    assert.equal(entries.has(`${origin}${bundle}`), true);
});

test('activation removes old asset caches and retains current and unrelated caches', async () => {
    const app = fixture();
    for (const name of ['hawki-pwa-assets-v0', 'hawki-pwa-offline-v1', 'unrelated-cache']) app.stores.set(name, new Map());
    await app.activate();
    assert.equal(app.stores.has('hawki-pwa-assets-v0'), false);
    for (const name of [assetCache, 'hawki-pwa-offline-v1', 'unrelated-cache']) assert.equal(app.stores.has(name), true);
});
