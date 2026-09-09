import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {ClientExtension} from '../../../resources/js/kernel/client/ClientExtension.svelte.js';
import {ApiTransportError} from '../../../resources/js/kernel/api/errors.js';
import {authMetaGuards} from '../../../resources/js/kernel/routing/middlewares/AuthMiddleware.js';
import {RouteRedirect} from '../../../resources/js/components/ui/routing/logistics/signals.js';

const authenticated = {
    id: 'hawki', type: 'internal_authenticated', isAuthenticated: true, hasUserInfo: true,
    version: '1', locale: 'en_US', keychain_state: 'initialized',
    userinfo: {id: 1, name: 'Alice', username: 'alice', email: 'alice@example.test', avatar: null, bio: null, hash: 'alice'}
};
const registering = {
    id: 'hawki', type: 'internal_registering_user', isAuthenticated: false, hasUserInfo: true,
    version: '1', locale: 'en_US', userinfo: {name: 'Alice', username: 'alice', email: 'alice@example.test'}
};
const guest = {id: 'hawki', type: 'internal', isAuthenticated: false, hasUserInfo: false, version: '1', locale: 'en_US'};

function installBrowser(pathname: string, search = '') {
    const destinations: string[] = [];
    Object.assign(globalThis, {
        window: {
            location: {origin: 'https://hawki.test', pathname, search, hash: '', assign: (url: string) => destinations.push(url)},
            setInterval: () => 1,
            clearInterval: () => {},
            addEventListener: () => {},
            removeEventListener: () => {}
        },
        document: {
            cookie: 'XSRF-TOKEN=test',
            documentElement: {lang: 'en_US'},
            querySelector: () => null,
            addEventListener: () => {},
            removeEventListener: () => {}
        }
    });
    return destinations;
}

function clientFixture(connection: any) {
    let clears = 0;
    const handlers = new Map<string, (payload: any) => Promise<void>>();
    const events: any = {async: {
        on: (name: string, callback: (payload: any) => Promise<void>) => {
            handlers.set(name, callback);
            return () => {};
        },
        trigger: async () => {}
    }};
    const client = new ClientExtension(events);
    (client as any).connectionHandle.currentConnection = connection;
    const app: any = {
        events,
        connectionOrNull: connection,
        get logoutState() { return client.provideProperties().logoutState; },
        stores: {get: () => ({cryptoReady: true, lock: () => { clears++; }})},
        passkeySession: {clear: () => {}},
        getOrFail: () => new Map(),
        config: {refresh: async () => {}}
    };
    client.ready(app);
    return {client, app, handlers, clears: () => clears};
}

test('401 during handshake clears secrets and redirects with the original next', async () => {
    const destinations = installBrowser('/new/auth/handshake', '?next=%2Fnew%2Fchat%3Fthread%3D1');
    const {client, clears} = clientFixture(authenticated);
    const previousFetch = globalThis.fetch;
    globalThis.fetch = async () => new Response('', {status: 401});
    try {
        await assert.rejects(client.client.restApi.fetch('/expired'));
        assert.equal(clears(), 1);
        assert.deepEqual(destinations, ['/new/auth/login?next=%2Fnew%2Fchat%3Fthread%3D1']);
        assert.equal(client.provideProperties().cryptoReady, false);
    } finally {
        globalThis.fetch = previousFetch;
    }
});

test('419 during registration also expires a session with user info', async () => {
    const destinations = installBrowser('/new/auth/register', '?next=%2Fnew%2Fchat');
    const {client, clears} = clientFixture(registering);
    const previousFetch = globalThis.fetch;
    globalThis.fetch = async () => new Response('', {status: 419});
    try {
        await assert.rejects(client.client.restApi.fetch('/registration', {method: 'POST'}));
        assert.equal(clears(), 1);
        assert.deepEqual(destinations, ['/new/auth/login?next=%2Fnew%2Fchat']);
    } finally {
        globalThis.fetch = previousFetch;
    }
});

test('guest credential rejection remains an API error without a login redirect loop', async () => {
    const destinations = installBrowser('/new/auth/login', '?next=%2Fnew%2Fchat');
    const {client, clears} = clientFixture(guest);
    const previousFetch = globalThis.fetch;
    globalThis.fetch = async () => new Response(JSON.stringify({errors: [{code: 'invalid_credentials', detail: 'Invalid login'}]}), {status: 401});
    try {
        await assert.rejects(
            client.client.restApi.fetch('/login', {method: 'POST'}),
            error => error instanceof ApiTransportError && error.code === 'invalid_credentials'
        );
        assert.equal(clears(), 0);
        assert.deepEqual(destinations, []);
    } finally {
        globalThis.fetch = previousFetch;
    }
});

test('background expiry and identity changes leave auth pages and clear old secrets', async () => {
    const destinations = installBrowser('/new/auth/handshake', '?next=%2Fnew%2Fchat');
    const expired = clientFixture(authenticated);
    expired.client.init(expired.app, {onPreparationStage: () => {}} as any);
    await expired.handlers.get('connectionChanged')!(guest);
    assert.equal(expired.clears(), 1);
    assert.deepEqual(destinations, ['/new/auth/login?next=%2Fnew%2Fchat']);

    destinations.length = 0;
    window.location.pathname = '/new/auth/register';
    const changed = clientFixture(registering);
    changed.client.init(changed.app, {onPreparationStage: () => {}} as any);
    await changed.handlers.get('connectionChanged')!(authenticated);
    assert.equal(changed.clears(), 1);
    assert.deepEqual(destinations, ['/new/auth/handshake?next=%2Fnew%2Fchat']);
});

test('a guest entering handshake retains the original destination through its route guard', async () => {
    installBrowser('/new/auth/handshake', '?next=%2Fnew%2Fchat%3Fthread%3D1');
    const {app} = clientFixture(guest);
    const guard = authMetaGuards({access: 'server-session'});

    await assert.rejects(
        guard({app, pathname: '/new/auth/handshake'} as any, async () => undefined),
        error => error instanceof RouteRedirect && error.target === 'auth.login'
            && error.params?.next === '/new/chat?thread=1'
    );
});

test('background session changes cannot bypass a pending or failed provider logout', async () => {
    const destinations = installBrowser('/new/chat');
    const {client, app, handlers} = clientFixture(authenticated);
    client.init(app, {onPreparationStage: () => {}} as any);
    const clientChanged = handlers.get('connectionChanged')!;
    authMetaGuards({access: 'crypto-ready'}).effect({app} as any);
    const guardChanged = handlers.get('connectionChanged')!;
    const previousFetch = globalThis.fetch;
    let rejectRequest!: (reason: Error) => void;
    const response = new Promise<Response>((_resolve, reject) => { rejectRequest = reject; });
    globalThis.fetch = () => response;
    try {
        const logoutFailure = assert.rejects(client.logout(), /offline/);
        await clientChanged(guest);
        await guardChanged(guest);
        assert.equal(app.logoutState, 'pending');
        assert.deepEqual(destinations, []);

        rejectRequest(new Error('offline'));
        await logoutFailure;
        await clientChanged(guest);
        await guardChanged(guest);
        assert.equal(app.logoutState, 'failed');
        assert.deepEqual(destinations, []);

        globalThis.fetch = async () => new Response(JSON.stringify({redirect_url: 'https://idp.test/logout'}));
        await client.logout();
        assert.deepEqual(destinations, ['https://idp.test/logout']);
    } finally {
        globalThis.fetch = previousFetch;
    }
});
