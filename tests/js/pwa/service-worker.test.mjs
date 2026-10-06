import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {test} from 'node:test';
import vm from 'node:vm';

const workerSource = readFileSync(new URL('../../../public/sw.js', import.meta.url), 'utf8');

function response({ok = true, redirected = false, contentType = 'text/html; charset=utf-8'} = {}) {
    return {
        ok,
        status: ok ? 200 : 500,
        redirected,
        headers: new Headers({'content-type': contentType}),
        clone() { return this; }
    };
}

function worker({fetch = async () => response(), cacheNames = ['hawki-pwa-offline-v1']} = {}) {
    const handlers = new Map();
    const puts = [];
    const deleted = [];
    const matches = [];
    let claimed = 0;
    const cache = {
        put: async (...args) => puts.push(args),
        match: async (...args) => {
            matches.push(args);
            return response();
        }
    };
    const context = {
        URL,
        fetch,
        caches: {
            open: async () => cache,
            keys: async () => cacheNames,
            delete: async name => deleted.push(name)
        },
        self: {
            location: {origin: 'https://hawki.test'},
            clients: {claim: async () => { claimed += 1; }},
            addEventListener: (name, listener) => handlers.set(name, listener)
        }
    };
    vm.runInNewContext(workerSource, context, {filename: 'sw.js'});
    return {handlers, puts, deleted, matches, claimed: () => claimed};
}

async function waitFor(handler) {
    let work;
    handler({waitUntil: promise => { work = promise; }});
    await work;
}

function fetchEvent(request) {
    let handled;
    return {
        request: {headers: new Headers(), cache: 'default', ...request},
        waitUntil: () => {},
        respondWith: promise => { handled = promise; },
        response: () => handled
    };
}

test('caches only the public offline HTML without credentials', async () => {
    const calls = [];
    const subject = worker({fetch: async (...args) => {
        calls.push(args);
        return response();
    }});

    await waitFor(subject.handlers.get('install'));

    assert.equal(calls.length, 1);
    assert.equal(calls[0][0], '/pwa/offline.html');
    assert.equal(calls[0][1].credentials, 'omit');
    assert.equal(calls[0][1].cache, 'reload');
    assert.equal(subject.puts.length, 1);
    assert.equal(subject.puts[0][0], '/pwa/offline.html');
});

test('refuses redirects, failures, and non-HTML responses during installation', async () => {
    for (const invalid of [response({ok: false}), response({redirected: true}), response({contentType: 'application/json'})]) {
        const subject = worker({fetch: async () => invalid});
        await assert.rejects(waitFor(subject.handlers.get('install')));
        assert.equal(subject.puts.length, 0);
    }
});

test('intercepts only same-origin GET navigations under the new frontend', () => {
    const subject = worker();
    const fetchHandler = subject.handlers.get('fetch');
    for (const request of [
        {url: 'https://hawki.test/new', method: 'GET', mode: 'navigate'},
        {url: 'https://hawki.test/new/chat/42', method: 'GET', mode: 'navigate'}
    ]) {
        const event = fetchEvent(request);
        fetchHandler(event);
        assert.ok(event.response());
    }
    for (const request of [
        {url: 'https://hawki.test/newness', method: 'GET', mode: 'navigate'},
        {url: 'https://hawki.test/api/chat', method: 'GET', mode: 'navigate'},
        {url: 'https://hawki.test/chat', method: 'GET', mode: 'navigate'},
        {url: 'https://hawki.test/api/chat', method: 'GET', mode: 'cors'},
        {url: 'https://hawki.test/new/chat', method: 'POST', mode: 'navigate'},
        {url: 'https://hawki.test/new/app.js', method: 'GET', mode: 'no-cors'},
        {url: 'https://elsewhere.test/new/chat', method: 'GET', mode: 'navigate'}
    ]) {
        const event = fetchEvent(request);
        fetchHandler(event);
        assert.equal(event.response(), undefined);
    }
});

test('returns network HTTP responses unchanged and falls back only when networking fails', async () => {
    const serverFailure = response({ok: false});
    const online = worker({fetch: async () => serverFailure});
    const onlineEvent = fetchEvent({url: 'https://hawki.test/new/chat', method: 'GET', mode: 'navigate'});
    online.handlers.get('fetch')(onlineEvent);
    assert.equal(await onlineEvent.response(), serverFailure);
    assert.equal(online.matches.length, 0);
    assert.equal(online.puts.length, 0);

    const offline = worker({fetch: async () => { throw new TypeError('offline'); }});
    const offlineEvent = fetchEvent({url: 'https://hawki.test/new/auth/login', method: 'GET', mode: 'navigate'});
    offline.handlers.get('fetch')(offlineEvent);
    await offlineEvent.response();
    assert.deepEqual(offline.matches, [['/pwa/offline.html']]);
    assert.equal(offline.puts.length, 0);
});

test('claims clients and removes only superseded HAWKI offline caches', async () => {
    const subject = worker({cacheNames: [
        'hawki-pwa-offline-v1',
        'hawki-pwa-offline-v0',
        'other-app-v3',
        'hawki-pwa-assets-v1'
    ]});

    await waitFor(subject.handlers.get('activate'));

    assert.deepEqual(subject.deleted, ['hawki-pwa-offline-v0']);
    assert.equal(subject.claimed(), 1);
});
