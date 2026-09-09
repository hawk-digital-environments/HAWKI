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
    assert.deepEqual(events, ['connected', 'connectionChanged']);
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
    assert.deepEqual(events, ['connected', 'connectionChanged']);
});
