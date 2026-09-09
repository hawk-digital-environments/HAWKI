import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {webcrypto} from 'node:crypto';
import {KeychainStore} from '../../../resources/js/plugins/core/stores/KeychainStore.svelte.js';
import {ClientExtension} from '../../../resources/js/kernel/client/ClientExtension.svelte.js';
import {deriveKey, exportCryptoKeyToString} from '../../../resources/js/kernel/encryption/utils.js';
import {encryptSymmetric} from '../../../resources/js/kernel/encryption/symmetric.js';

Object.assign(globalThis, {window: {crypto: webcrypto}});

async function fixture() {
    const calls: string[] = [];
    const passkey = 'correct-passkey';
    const key = await deriveKey(passkey, 'keychain_encryptor', 'test-salt');
    const rsa = await webcrypto.subtle.generateKey({name: 'RSA-OAEP', modulusLength: 2048, publicExponent: new Uint8Array([1, 0, 1]), hash: 'SHA-256'}, true, ['encrypt', 'decrypt']);
    const ai = await webcrypto.subtle.generateKey({name: 'AES-GCM', length: 256}, true, ['encrypt', 'decrypt']);
    const values = await Promise.all([
        {key: 'publicKey', type: 'public_key', cryptoKey: rsa.publicKey, format: 'spki'},
        {key: 'privateKey', type: 'private_key', cryptoKey: rsa.privateKey, format: 'pkcs8'},
        {key: 'aiConvKey', type: 'ai_conv', cryptoKey: ai, format: 'raw'}
    ].map(async item => ({key: item.key, type: item.type, value: (await encryptSymmetric(await exportCryptoKeyToString(item.cryptoKey, item.format), key)).toString()})));
    const storage = new Map<string, string>();
    const connection = {isAuthenticated: true, hasUserInfo: true, keychain_state: 'legacy_migration_required', userinfo: {username: 'alice', email: 'alice@example.test'}};
    let migrated = false;
    const listeners = new Map<string, Array<() => void>>();
    const app: any = {
        connection, connectionOrNull: connection,
        config: {get: () => ({salts: {userdata: 'test-salt', passkey: 'local-salt', ai: 'ai-salt'}})},
        passkeySession: {passkey: null, clear() { this.passkey = null; }},
        localStorage: {getItem: (key: string) => storage.get(key) ?? null, setItem: (key: string, value: string) => storage.set(key, value), removeItem: (key: string) => storage.delete(key)},
        restApi: {
            getFromResourceAction: async () => { calls.push('validator'); return {validator: values[0].value}; },
            getResourceCollection: async () => { calls.push('keychain'); assert.equal(migrated, true); return values; }
        },
        migration: {apply: async (run: string) => { calls.push(run); migrated = true; }},
        events: {async: {
            on: (name: string, callback: () => void) => listeners.set(name, [...(listeners.get(name) ?? []), callback]),
            trigger: async (name: string) => { for (const callback of listeners.get(name) ?? []) await callback(); }
        }}
    };
    const store = new KeychainStore();
    store.ready(app);
    return {store, app, calls, storage, passkey};
}

test('manual unlock enforces migration before reading keys and explicit removal forgets them', async () => {
    const {store, app, calls, storage, passkey} = await fixture();
    assert.equal(await store.unlock('wrong-passkey'), false);
    assert.equal(store.cryptoReady, false);
    calls.length = 0;
    assert.equal(await store.unlock(passkey), true);
    assert.deepEqual(calls, ['validator', 'after_passkey', 'keychain']);
    assert.equal(store.cryptoReady, true);
    await store.persistPasskey(passkey);
    assert.ok(storage.has('alicePK'));
    store.clearLocalSession();
    assert.equal(storage.has('alicePK'), false);
    assert.equal(store.cryptoReady, false);
    assert.equal(store.privateKey, null);
    assert.equal(store.publicKey, null);
    assert.equal(store.aiConvKey, null);
    assert.equal(app.passkeySession.passkey, null);
});

test('local passkey restoration follows the same migration chain', async () => {
    const {store, app, passkey, calls} = await fixture();
    await store.persistPasskey(passkey);
    await store.loadData(app);
    assert.deepEqual(calls, ['validator', 'after_passkey', 'keychain']);
    assert.equal(store.cryptoReady, true);
});

test('failed migrations clear decrypted keys but retain the encrypted passkey for retry', async () => {
    const {store, app, passkey, calls, storage} = await fixture();
    await store.persistPasskey(passkey);
    const saved = storage.get('alicePK');
    app.migration.apply = async () => { throw new Error('migration failed'); };
    await assert.rejects(store.unlock(passkey), /migration failed/);
    assert.deepEqual(calls, ['validator']);
    assert.equal(store.cryptoReady, false);
    assert.equal(app.passkeySession.passkey, null);
    assert.equal(storage.get('alicePK'), saved);
});

test('logout while restoring browser storage cannot resurrect a passkey', async () => {
    const {store, app, passkey, calls, storage} = await fixture();
    await store.persistPasskey(passkey);
    const restoring = store.loadData(app);
    await app.events.async.trigger('logout');
    await restoring;
    assert.deepEqual(calls, []);
    assert.equal(store.cryptoReady, false);
    assert.equal(app.passkeySession.passkey, null);
    assert.equal(storage.has('alicePK'), true);
});

test('logout during migration prevents an in-flight unlock from restoring keys or deleting the saved passkey', async () => {
    const {store, app, passkey, calls, storage} = await fixture();
    await store.persistPasskey(passkey);
    let started!: () => void;
    const migrationStarted = new Promise<void>(resolve => {started = resolve;});
    let finish!: () => void;
    app.migration.apply = () => {started(); return new Promise<void>(resolve => {finish = resolve;});};
    const unlocking = store.unlock(passkey);
    await migrationStarted;
    await app.events.async.trigger('logout');
    finish();
    await assert.rejects(unlocking, /cleared/);
    assert.deepEqual(calls, ['validator']);
    assert.equal(store.cryptoReady, false);
    assert.equal(store.privateKey, null);
    assert.equal(app.passkeySession.passkey, null);
    assert.equal(storage.has('alicePK'), true);
});

test('logout and retry preserve the encrypted passkey and the next login restores it automatically', async () => {
    const {store, app, passkey, storage} = await fixture();
    const destinations: string[] = [];
    Object.assign(window, {location: {origin: 'https://hawki.test', pathname: '/new/chat', search: '', hash: '', assign: (url: string) => destinations.push(url)}});
    Object.assign(globalThis, {document: {cookie: 'XSRF-TOKEN=test', querySelector: () => null}});
    await store.persistPasskey(passkey);
    await store.unlock(passkey);
    const saved = storage.get('alicePK');
    assert.ok(saved && !saved.includes(passkey));
    assert.ok(JSON.parse(saved).ciphertext);

    app.stores = {get: () => store};
    const client = new ClientExtension(app.events);
    client.ready(app);
    const previousFetch = globalThis.fetch;
    globalThis.fetch = async () => {
        assert.equal(store.cryptoReady, false);
        assert.equal(store.privateKey, null);
        assert.equal(store.publicKey, null);
        assert.equal(store.aiConvKey, null);
        assert.deepEqual(store.roomKeys, {});
        assert.equal(app.passkeySession.passkey, null);
        assert.equal(storage.get('alicePK'), saved);
        throw new Error('offline');
    };
    try {
        await assert.rejects(client.logout(), /offline/);
        globalThis.fetch = async () => new Response(JSON.stringify({redirect_url: null}));
        await client.logout();
        assert.deepEqual(destinations, ['/new/auth/login']);
        assert.equal(storage.get('alicePK'), saved);

        app.connection.isAuthenticated = false;
        const nextStore = new KeychainStore();
        nextStore.ready(app);
        await nextStore.loadData(app);
        assert.equal(nextStore.cryptoReady, false);
        assert.equal(app.passkeySession.passkey, null);

        app.connection.isAuthenticated = true;
        await nextStore.loadData(app);
        assert.equal(nextStore.cryptoReady, true);
        assert.equal(app.passkeySession.passkey, passkey);
    } finally {
        globalThis.fetch = previousFetch;
    }
});

test('a different account cannot restore the previous account passkey', async () => {
    const {store, app, passkey, storage, calls} = await fixture();
    await store.persistPasskey(passkey);
    await app.events.async.trigger('logout');
    const alice = app.connection;
    app.connection = {...alice, userinfo: {username: 'bob', email: 'bob@example.test'}};
    await store.loadData(app);
    assert.equal(store.cryptoReady, false);
    assert.equal(app.passkeySession.passkey, null);
    assert.deepEqual(calls, []);
    assert.ok(storage.has('alicePK'));

    app.connection = alice;
    await store.loadData(app);
    assert.equal(store.cryptoReady, true);
});

test('an unlock cancelled by logout cannot lock a later successful login', async () => {
    const {store, app, passkey, storage} = await fixture();
    await store.persistPasskey(passkey);
    const saved = storage.get('alicePK');
    const migrate = app.migration.apply;
    let started!: () => void;
    const migrationStarted = new Promise<void>(resolve => { started = resolve; });
    let finish!: () => void;
    app.migration.apply = () => { started(); return new Promise<void>(resolve => { finish = resolve; }); };
    const staleUnlock = store.unlock(passkey);
    await migrationStarted;
    await app.events.async.trigger('logout');

    app.migration.apply = migrate;
    assert.equal(await store.unlock(passkey), true);
    finish();
    await assert.rejects(staleUnlock, /cleared/);
    assert.equal(store.cryptoReady, true);
    assert.ok(store.privateKey);
    assert.equal(app.passkeySession.passkey, passkey);
    assert.equal(storage.get('alicePK'), saved);
});
