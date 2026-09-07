import test from 'node:test';
import assert from 'node:assert/strict';
import {flushSync, tick} from 'svelte';
import {readable} from 'svelte/store';
import {SearchExtension} from '$lib/kernel/search/SearchExtension.svelte.js';
import {SearchRegistry} from '$lib/kernel/search/searchRegistry.js';
import {createModuleRegistrar} from '$lib/kernel/modules/moduleRegistrar.js';
import {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {HawkiModuleWithPlugin} from '$lib/kernel/modules/types.js';
import type {HawkiPluginWithMetadata} from '$lib/kernel/plugins/types.js';
import type {ModuleSearchRegistrar, SearchEntry} from '$lib/kernel/search/types.js';

async function fixture(declare: (registrar: ModuleSearchRegistrar) => void) {
    const connection = $state({id: 'hawki', isAuthenticated: true, userinfo: {id: 1, hash: 'user-one'}});
    const registry = new SearchRegistry();
    const module = {name: 'test', plugin: {name: 'core'}, title: () => 'Test', search: declare} as HawkiModuleWithPlugin;
    registry.registerModule('core:test', module);
    const callbacks = new Map<string, () => void>();
    const storage = new Map<string, string>();
    const app = {
        modules: {searchRegistry: registry, get: () => module},
        translator: {translate: (key: string) => key},
        localization: {locale: {lang: 'en_US'}},
        get connectionOrNull() {return connection;},
        events: {async: {on: (name: string, callback: () => void) => {callbacks.set(name, callback); return () => callbacks.delete(name);}}},
        localStorage: {getItem: (key: string) => storage.get(key) ?? null, setItem: (key: string, value: string) => storage.set(key, value)}
    } as unknown as HawkiApp;
    const search = new SearchExtension();
    const bootstrapper = new Bootstrapper();
    search.ready(app, bootstrapper);
    await bootstrapper.run();
    await tick();
    return {search, registry, connection, callbacks};
}

function entry(id: string, title: string): SearchEntry {
    return {id, entityKey: id, title, onSelect() {}};
}

const titles = (session: ReturnType<SearchExtension['createSession']>) => session.state.groups.flatMap(group => group.items.map(row => row.title));

test('module search registration rolls back without evaluating provider runtime data', () => {
    const modules = new Map();
    const registry = new SearchRegistry();
    const plugin = {name: 'core'} as HawkiPluginWithMetadata;
    const registrar = createModuleRegistrar(modules, plugin, registry);
    let reads = 0;
    assert.throws(() => registrar.add({name: 'bad', search({group}) {
        const local = group('items', {kind: 'static', label: () => 'Items'});
        local.add('same', {items: () => {reads++; return [];}});
        local.add('same', {items: () => []});
    }}));
    assert.equal(modules.size, 0);
    assert.equal(registry.allProviders.length, 0);
    assert.equal(reads, 0);
    registrar.add({name: 'good', search({group}) {
        group('items', {kind: 'static', label: () => 'Items'}).add('items', {items: () => {reads++; return [];}});
    }});
    assert.equal(reads, 0);
    assert.equal(registry.allProviders.length, 1);
});

test('two bars share activation and observe nested edits, disablement and reactivation', async () => {
    const data = $state({enabled: true, rows: [entry('one', 'Design')]});
    let loads = 0;
    const {search} = await fixture(({group}) => {
        group('items', {kind: 'static', label: () => 'Items'}).add('items', {
            enabled: () => data.enabled,
            items: () => data.rows,
            load: () => {loads++;}
        });
    });
    try {
        const first = search.createSession();
        const second = search.createSession();
        first.setInput({query: 'design'});
        second.setInput({query: ''});
        assert.deepEqual(titles(first), ['Design']);
        assert.equal(loads, 1);
        flushSync(() => {data.rows[0].title = 'Renamed';});
        assert.deepEqual(titles(first), []);
        assert.deepEqual(titles(second), ['Renamed']);
        flushSync(() => {data.enabled = false;});
        assert.deepEqual(titles(second), []);
        flushSync(() => {data.enabled = true;});
        await tick();
        assert.equal(loads, 2);
        assert.deepEqual(titles(second), ['Renamed']);
        first.dispose();
        const reopened = search.createSession();
        assert.equal(loads, 2);
        assert.deepEqual(titles(reopened), ['Renamed']);
        reopened.dispose(); second.dispose();
    } finally {search.dispose();}
});

test('readable sources subscribe before loading and release on module removal', async () => {
    let subscriptions = 0;
    let stopped = 0;
    let setItems: (items: readonly SearchEntry[]) => void = () => {};
    const source = readable<readonly SearchEntry[]>([], set => {
        subscriptions++;
        setItems = set;
        return () => {stopped++;};
    });
    const {search, registry} = await fixture(({group}) => {
        group('items', {kind: 'static', label: () => 'Items'}).add('items', {
            items: () => source,
            load: () => setItems([entry('loaded', 'Loaded')])
        });
    });
    try {
        const session = search.createSession();
        assert.equal(subscriptions, 1);
        assert.deepEqual(titles(session), ['Loaded']);
        session.freezeOrder();
        registry.unregisterModule('core:test');
        flushSync();
        assert.equal(stopped, 1);
        assert.equal(session.select('loaded'), null);
        assert.equal(session.state.groups[0]?.items[0]?.available, false);
        session.dispose();
    } finally {search.dispose();}
});

test('logout clears active search rows and stops source observation', async () => {
    const rows = $state([entry('private', 'Private')]);
    const {search, callbacks} = await fixture(({group}) => {
        group('items', {kind: 'static', label: () => 'Items'}).add('items', {items: () => rows});
    });
    const session = search.createSession();
    assert.deepEqual(titles(session), ['Private']);
    callbacks.get('logout')!();
    flushSync(() => {rows.push(entry('late', 'Late'));});
    assert.deepEqual(titles(session), []);
    assert.equal(session.select('private'), null);
    search.dispose();
});

test('static load errors and successful retries notify reactive consumers', async () => {
    let failLoad: (reason: Error) => void = () => {};
    let loads = 0;
    const {search} = await fixture(({group}) => {
        group('items', {kind: 'static', label: () => 'Items'}).add('items', {
            items: () => [entry('local', 'Local')],
            load: () => {
                if (++loads === 1) return new Promise<void>((_, reject) => {failLoad = reject;});
            }
        });
    });
    const session = search.createSession();
    let errorCount = -1;
    const stop = $effect.root(() => {
        $effect(() => {errorCount = session.state.providerErrors.length;});
    });
    try {
        await tick();
        assert.equal(errorCount, 0);
        failLoad(new Error('Offline'));
        await new Promise(resolve => setTimeout(resolve, 0));
        await tick();
        assert.equal(errorCount, 1);
        session.retry(session.state.providerErrors[0].providerId);
        await tick();
        assert.equal(errorCount, 0);
        assert.equal(loads, 2);
    } finally {stop(); search.dispose();}
});
