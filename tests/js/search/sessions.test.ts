import test from 'node:test';
import assert from 'node:assert/strict';
import {SearchSessionManager} from '$lib/kernel/search/SearchSessionManager.svelte.js';
import {documentKey} from '$lib/kernel/search/sharedIndex.js';
import {TestSearchHost, staticSourceOf, entry, tick, rowTitles} from './harness.js';

function manager(host: TestSearchHost) {
    const result = new SearchSessionManager(host);
    host.index.subscribe(() => result.invalidate());
    return result;
}

function deferred<T>() {
    let resolve!: (value: T) => void;
    const promise = new Promise<T>(done => resolve = done);
    return {promise, resolve};
}

test('selection resolves the latest callback while the frozen label stays unchanged', () => {
    const host = new TestSearchHost();
    let selected = '';
    const rows = [entry('one', 'Design', {onSelect: () => selected = 'old'})];
    host.register('core:chat', 'core', ({group}) => group('titles', {kind: 'static', label: () => 'Titles'}).add('titles', staticSourceOf(rows)));
    const api = manager(host);
    try {
        const session = api.createSession();
        session.freezeOrder();
        rows[0] = entry('one', 'Renamed', {onSelect: () => selected = 'current'});
        host.reindex('core:chat.titles');
        assert.deepEqual(rowTitles(session.state.groups), ['Design']);
        session.select('entity/one')!.onSelect();
        assert.equal(selected, 'current');
        rows[0] = entry('one', 'Different entity', {entityKey: 'other'});
        host.reindex('core:chat.titles');
        assert.equal(session.select('entity/one'), null);
        assert.equal(session.state.groups[0].items[0].available, false);
    } finally {api.dispose();}
});

test('server groups publish together, use Orama scores, and retain server-only matches', async () => {
    const host = new TestSearchHost();
    const slow = deferred<ReturnType<typeof entry>[]>();
    host.register('core:test', 'core', ({group}) => {
        const remote = group('remote', {kind: 'dynamic', label: () => 'Server'});
        remote.add('first', {search: async () => [entry('hidden', 'Roadmap'), entry('strong', 'Design')]});
        remote.add('second', {search: () => slow.promise});
    });
    const api = manager(host);
    try {
        const session = api.createSession();
        session.setInput({query: 'design'});
        await tick(280);
        assert.deepEqual(session.state.groups, []);
        slow.resolve([entry('second', 'Unrelated')]);
        await tick();
        assert.deepEqual(rowTitles(session.state.groups), ['Design', 'Roadmap', 'Unrelated']);
        assert.equal(session.state.remotePending, false);
        session.select('entity/strong');
        assert.equal(host.storage.items.size, 0);
    } finally {api.dispose();}
});

test('disabled dynamic callbacks cannot return into the current query', async () => {
    const host = new TestSearchHost();
    const pending = deferred<ReturnType<typeof entry>[]>();
    let calls = 0;
    host.register('core:test', 'core', ({group}) => group('remote', {kind: 'dynamic', label: () => 'Remote'})
        .add('remote', {search: () => {calls++; return pending.promise;}}));
    const api = manager(host);
    try {
        const session = api.createSession();
        session.setInput({query: 'design'});
        await tick(280);
        host.deactivate('core:test.remote'); api.invalidate();
        pending.resolve([entry('old', 'Design old')]);
        await tick();
        assert.equal(calls, 1);
        assert.deepEqual(session.state.groups, []);
        assert.equal(session.state.remotePending, false);
    } finally {api.dispose();}
});

test('a server-only match uses an available static copy and records static history', async () => {
    const host = new TestSearchHost();
    host.register('core:test', 'core', ({group}) => {
        group('local', {kind: 'static', label: () => 'Local'}).add('local', staticSourceOf([entry('local', 'Roadmap', {entityKey:'shared'})]));
        group('remote', {kind: 'dynamic', label: () => 'Remote'}).add('remote', {search: async () => [entry('remote', 'Roadmap', {entityKey:'shared'})]});
    });
    const api = manager(host);
    try {
        const session = api.createSession();
        session.setInput({query: 'design'}); await tick(280);
        assert.equal(session.state.groups[0].id, 'core:test.local');
        assert.equal(session.select('shared')!.kind, 'static');
        const history = JSON.parse([...host.storage.items.values()][0]);
        assert.deepEqual(Object.keys(history[0]).sort(), ['at', 'entityKey']);
        const second = api.createSession();
        assert.deepEqual(rowTitles(second.state.groups), ['Roadmap']);
    } finally {api.dispose();}
});

test('IME composition cancels in-flight results and resumes the current query', async () => {
    const host = new TestSearchHost();
    const old = deferred<ReturnType<typeof entry>[]>();
    let calls = 0;
    host.register('core:test', 'core', ({group}) => group('remote', {kind: 'dynamic', label: () => 'Remote'})
        .add('remote', {search: async ({query}) => {calls++; return calls === 1 ? old.promise : [entry('new', query)];}}));
    const api = manager(host);
    try {
        const session = api.createSession();
        session.setInput({query: 'before'}); await tick(280);
        session.suspend(); session.setInput({query: 'after'});
        old.resolve([entry('old','before')]); await tick(280);
        assert.equal(calls, 1);
        assert.deepEqual(session.state.groups, []);
        session.suspend(false); await tick();
        assert.deepEqual(rowTitles(session.state.groups), ['after']);
    } finally {api.dispose();}
});

test('worker replies from an old revision cannot restore a removed document', async () => {
    const host = new TestSearchHost();
    const replies: {revision:number; result:ReturnType<typeof deferred<any>>}[] = [];
    host.worker = () => {const result = deferred<any>(); replies.push({revision:host.index.revision,result}); return result.promise;};
    const rows = [entry('one', 'Design')];
    host.register('core:test', 'core', ({group}) => group('worker', {kind:'static',label:()=> 'Worker'}).add('worker', staticSourceOf(rows,'worker')));
    const api = manager(host);
    try {
        const session = api.createSession(); session.setInput({query:'design'});
        rows.splice(0); host.reindex('core:test.worker');
        replies[0].result.resolve({revision:replies[0].revision,scores:[[documentKey('core:test.worker','one'),10]]});
        await tick();
        assert.deepEqual(session.state.groups, []);
        assert.equal(session.state.localPending, false);
    } finally {api.dispose();}
});

test('unknown and conflicting scopes issue no requests', async () => {
    const host = new TestSearchHost(); let calls=0;
    host.register('core:test','core',({group})=>group('remote',{kind:'dynamic',label:()=> 'Remote'}).add('remote',{search:async()=>{calls++;return [];}}));
    const api = manager(host);
    try {
        const session=api.createSession({allowedScope:{pluginId:'other'}});
        session.setInput({query:'design',scope:{moduleId:'core:test'}}); await tick(280);
        assert.equal(calls,0); assert.deepEqual(session.state.groups,[]);
    } finally {api.dispose();}
});
