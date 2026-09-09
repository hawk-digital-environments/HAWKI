import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {ClientExtension} from '../../../resources/js/kernel/client/ClientExtension.svelte.js';
import {ApiTransportError} from '../../../resources/js/kernel/api/errors.js';

test('logout clears local secrets before a failed request and allows retry', async () => {
    let secret: string | null = 'passkey';
    const destinations: string[] = [];
    Object.assign(globalThis, {
        window: {location: {origin: 'https://hawki.test', pathname: '/new/chat', search: '', hash: '', assign: (url: string) => destinations.push(url)}},
        document: {cookie: 'XSRF-TOKEN=test', querySelector: () => null}
    });
    const events: any = {async: {trigger: async () => assert.equal(secret, null)}};
    const client = new ClientExtension(events);
    const app: any = {stores: {get: () => ({lock: () => {secret = null;}})}, passkeySession: {clear: () => {secret = null;}}};
    client.ready(app);
    const oldFetch = globalThis.fetch;
    globalThis.fetch = async () => {
        assert.equal(secret, null);
        throw new Error('offline');
    };
    try {
        await assert.rejects(client.logout(), /offline/);
        assert.equal(client.provideProperties().logoutState, 'failed');
        assert.deepEqual(destinations, []);
        globalThis.fetch = async () => new Response(JSON.stringify({redirect_url: 'https://idp.test/logout'}));
        await client.logout();
        assert.equal(secret, null);
        assert.deepEqual(destinations, ['https://idp.test/logout']);
    } finally {
        globalThis.fetch = oldFetch;
    }
});

test('419 during logout remains failed and retryable without redirecting', async () => {
    let clears = 0;
    const destinations: string[] = [];
    Object.assign(globalThis, {
        window: {location: {origin: 'https://hawki.test', pathname: '/new/chat', search: '', hash: '', assign: (url: string) => destinations.push(url)}},
        document: {cookie: 'XSRF-TOKEN=test', querySelector: () => null}
    });
    const events: any = {async: {trigger: async () => {}}};
    const client = new ClientExtension(events);
    const app: any = {
        stores: {get: () => ({lock: () => {clears++;}})},
        passkeySession: {clear: () => {}}
    };
    client.ready(app);
    const previousFetch = globalThis.fetch;
    globalThis.fetch = async () => new Response('', {status: 419});
    try {
        await assert.rejects(client.logout(), error => error instanceof ApiTransportError && error.status === 419);
        assert.equal(client.provideProperties().logoutState, 'failed');
        assert.deepEqual(destinations, []);
        globalThis.fetch = async () => new Response(JSON.stringify({redirect_url: null}));
        await client.logout();
        assert.deepEqual(destinations, ['/new/auth/login']);
        assert.equal(clears, 2);
    } finally {
        globalThis.fetch = previousFetch;
    }
});
