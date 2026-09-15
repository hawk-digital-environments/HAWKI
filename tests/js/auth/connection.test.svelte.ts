import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {ConnectionHandle} from '../../../resources/js/kernel/client/connection/ConnectionHandle.svelte.js';
import {ApiTransportError} from '../../../resources/js/kernel/api/errors.js';

Object.assign(globalThis, {document: {documentElement: {lang: 'en_US'}}, window: {setTimeout: () => {throw new Error('Auth rejection must not retry');}}});

function connection(username = 'alice') {
    return {id: 'hawki', type: 'internal_authenticated', isAuthenticated: true, hasUserInfo: true, version: '1', locale: 'en_US', keychain_state: 'initialized', userinfo: {id: username === 'alice' ? 1 : 2, hash: username}};
}

test('guest boot treats 401 and 403 as an internal connection without retries', async () => {
    for (const status of [401, 403]) {
        const events: string[] = [];
        const api: any = {getResource: async () => {throw new ApiTransportError(status, [], null, 'Denied');}};
        const eventBus: any = {async: {triggerVoid: async (name: string) => {events.push(name);}}};
        const handle = new ConnectionHandle(api, eventBus);
        assert.equal((await handle.refreshConnection()).type, 'internal');
        assert.deepEqual(events, ['connected']);
    }
});

test('session rejection drops the old identity immediately', async () => {
    const events: string[] = [];
    let reject = false;
    const api: any = {getResource: async () => {if (reject) throw new ApiTransportError(401, [], null, 'Expired'); return connection();}};
    const eventBus: any = {async: {triggerVoid: async (name: string) => {events.push(name);}}};
    const handle = new ConnectionHandle(api, eventBus);
    await handle.refreshConnection();
    reject = true;
    assert.equal((await handle.refreshConnection()).isAuthenticated, false);
    assert.deepEqual(events, ['connected', 'connectionRefreshStarted', 'connectionChanged']);
    assert.equal('userinfo' in handle.connection, false);
});

test('another authenticated user triggers an identity change despite an unchanged connection type', async () => {
    const events: string[] = [];
    let username = 'alice';
    const api: any = {getResource: async () => connection(username)};
    const eventBus: any = {async: {triggerVoid: async (name: string) => {events.push(name);}}};
    const handle = new ConnectionHandle(api, eventBus);
    await handle.refreshConnection();
    username = 'bob';
    await handle.refreshConnection();
    assert.deepEqual(events, ['connected', 'connectionRefreshStarted', 'connectionChanged']);
});

test('public-key hash changes do not change account identity', async () => {
    const events: string[] = [];
    let hash = 'old-public-key';
    const api: any = {getResource: async () => ({...connection(), userinfo: {id: 1, hash}})};
    const eventBus: any = {async: {triggerVoid: async (name: string) => {events.push(name);}}};
    const handle = new ConnectionHandle(api, eventBus);
    await handle.refreshConnection();
    hash = 'rotated-public-key';
    await handle.refreshConnection();
    assert.deepEqual(events, ['connected', 'connectionRefreshStarted', 'connectionRefreshed']);
});

test('permission-only refresh preserves connection identity and awaits one coalesced notification', async () => {
    const events: string[] = [];
    let calls = 0;
    let permissions = ['admin.access'];
    let release!: () => void;
    const listener = new Promise<void>(resolve => {release = resolve;});
    const api: any = {getResource: async () => {calls++; return {...connection(), userinfo: {id: 1, hash: 'same', permissions: [...permissions]}};}};
    const eventBus: any = {async: {triggerVoid: async (name: string) => {
        events.push(name);
        if (name === 'connectionRefreshed') await listener;
    }}};
    const handle = new ConnectionHandle(api, eventBus);
    const previous = await handle.refreshConnection();
    permissions = [];
    const first = handle.refreshConnection();
    const second = handle.refreshConnection();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(handle.refreshing, true);
    assert.equal(calls, 2);
    assert.equal(handle.connection, previous);
    assert.deepEqual((handle.connection as any).userinfo.permissions, []);
    release();
    await Promise.all([first, second]);
    assert.equal(handle.refreshing, false);
    assert.deepEqual(events, ['connected', 'connectionRefreshStarted', 'connectionRefreshed']);
});

test('a connection read completing after logout cannot publish an old identity', async () => {
    const events: string[] = [];
    let complete!: (value: unknown) => void;
    const api: any = {getResource: () => new Promise(resolve => {complete = resolve;})};
    const handle = new ConnectionHandle(api, {async: {triggerVoid: async (name: string) => {events.push(name);}}} as any);
    const request = handle.refreshConnection();
    await new Promise(resolve => setImmediate(resolve));
    handle.invalidate();
    complete(connection());
    await assert.rejects(request, /invalidated/);
    assert.equal(handle.tryGetConnection(), null);
    assert.deepEqual(events, []);
});
