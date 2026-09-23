import {describe, it} from 'node:test';
import assert from 'node:assert/strict';
import {SharedSearchIndex, documentKey, type IndexDelta, type IndexedProvider} from '$lib/kernel/search/sharedIndex.js';
import {UNRESTRICTED_SCOPE} from '$lib/kernel/search/scope.js';
import type {SearchEntry} from '$lib/kernel/search/types.js';

function provider(overrides: Partial<IndexedProvider> = {}): IndexedProvider {
    return {
        id: 'core:chat.conversations',
        groupId: 'core:chat.conversations',
        pluginId: 'core',
        moduleId: 'core:chat',
        kind: 'static',
        matchIn: 'immediate',
        order: 0,
        ...overrides
    };
}

function entry(id: string, title: string, overrides: Partial<SearchEntry> = {}): SearchEntry {
    return {id, entityKey: `ai-convs/${id}`, title, onSelect: () => {}, ...overrides};
}

/** Collects what the index would hand a worker. */
function sink(index: SharedSearchIndex): IndexDelta[] {
    const deltas: IndexDelta[] = [];
    index.setDeltaSink(delta => deltas.push(delta));
    return deltas;
}

function keys(index: SharedSearchIndex, query: string): string[] {
    return index.searchScores(query).map(([key]) => key);
}

describe('SharedSearchIndex', () => {
    it('indexes a provider snapshot and scores it', () => {
        const index = new SharedSearchIndex();
        assert.equal(index.setProviderEntries(provider(), [entry('a', 'Alpha release')]), true);

        assert.equal(index.revision, 1);
        assert.deepEqual(keys(index, 'alpha'), [documentKey('core:chat.conversations', 'a')]);
        assert.equal(index.has(documentKey('core:chat.conversations', 'a')), true);
    });

    it('ignores an unchanged snapshot', () => {
        const index = new SharedSearchIndex();
        index.setProviderEntries(provider(), [entry('a', 'Alpha')]);
        let notified = 0;
        index.subscribe(() => notified++);

        assert.equal(index.setProviderEntries(provider(), [entry('a', 'Alpha')]), false);
        assert.equal(index.revision, 1);
        assert.equal(notified, 0);
    });

    it('refreshes callbacks without reindexing', () => {
        const index = new SharedSearchIndex();
        index.setProviderEntries(provider(), [entry('a', 'Alpha')]);

        const onSelect = () => {};
        assert.equal(index.setProviderEntries(provider(), [entry('a', 'Alpha', {onSelect})]), false);
        assert.equal(index.revision, 1);
        assert.equal(index.document(documentKey('core:chat.conversations', 'a'))?.entry.onSelect, onSelect);
    });

    it('reindexes changed text and notifies once', () => {
        const index = new SharedSearchIndex();
        index.setProviderEntries(provider(), [entry('a', 'Alpha')]);
        let notified = 0;
        index.subscribe(() => notified++);

        assert.equal(index.setProviderEntries(provider(), [entry('a', 'Beta')]), true);
        assert.equal(index.revision, 2);
        assert.equal(notified, 1);
        assert.deepEqual(keys(index, 'alpha'), []);
        assert.deepEqual(keys(index, 'beta'), [documentKey('core:chat.conversations', 'a')]);
    });

    it('copies the fields it was handed', () => {
        const index = new SharedSearchIndex();
        const mutable = entry('a', 'Alpha', {keywords: ['one']});
        index.setProviderEntries(provider(), [mutable]);

        mutable.title = 'Beta';
        (mutable.keywords as string[]).push('two');

        const stored = index.document(documentKey('core:chat.conversations', 'a'))!;
        assert.equal(stored.entry.title, 'Alpha');
        assert.deepEqual(stored.entry.keywords, ['one']);
    });

    it('keeps document keys collision-proof', () => {
        assert.notEqual(documentKey('a#b', 'c'), documentKey('a', 'b#c'));

        const index = new SharedSearchIndex();
        index.setProviderEntries(provider({id: 'a#b'}), [entry('c', 'Alpha')]);
        index.setProviderEntries(provider({id: 'a', order: 1}), [entry('b#c', 'Beta')]);

        assert.equal(index.candidates(UNRESTRICTED_SCOPE).length, 2);
    });

    it('drops unusable and duplicate entries', () => {
        const index = new SharedSearchIndex();
        const errors = silenceErrors();
        try {
            index.setProviderEntries(provider(), [
                entry('a', 'Alpha'),
                entry('a', 'Alpha again'),
                {...entry('b', 'No key'), entityKey: ''},
                {...entry('c', 'No action'), onSelect: undefined as unknown as () => void}
            ]);
        } finally {
            errors.restore();
        }

        assert.equal(index.candidates(UNRESTRICTED_SCOPE).length, 1);
        assert.equal(errors.count, 3);
    });

    it('routes worker entries to the delta sink instead of the immediate engine', () => {
        const index = new SharedSearchIndex();
        const deltas = sink(index);
        const messages = provider({id: 'core:chat.messages', matchIn: 'worker', order: 1});

        index.setProviderEntries(messages, [entry('m1', 'Encrypted message')]);

        assert.equal(index.hasWorkerDocuments, true);
        assert.deepEqual(keys(index, 'encrypted'), []);
        assert.equal(deltas.length, 2); // The initial replay plus this change.
        assert.deepEqual(deltas[1].upserts.map(upsert => upsert.id), [documentKey('core:chat.messages', 'm1')]);
        assert.equal(deltas[1].revision, index.revision);
    });

    it('replays the worker corpus for a late sink', () => {
        const index = new SharedSearchIndex();
        index.setProviderEntries(provider({id: 'core:chat.messages', matchIn: 'worker'}), [entry('m1', 'Encrypted message')]);
        index.setProviderEntries(provider({id: 'core:chat.conversations', order: 1}), [entry('a', 'Alpha')]);

        const deltas = sink(index);

        assert.equal(deltas.length, 1);
        assert.equal(deltas[0].revision, index.revision);
        assert.deepEqual(deltas[0].upserts.map(upsert => upsert.id), [documentKey('core:chat.messages', 'm1')]);
    });

    it('reports removals to the worker and forgets the documents', () => {
        const index = new SharedSearchIndex();
        const messages = provider({id: 'core:chat.messages', matchIn: 'worker'});
        index.setProviderEntries(messages, [entry('m1', 'One'), entry('m2', 'Two')]);
        const deltas = sink(index);

        index.setProviderEntries(messages, [entry('m1', 'One')]);
        assert.deepEqual(deltas.at(-1)?.removals, [documentKey('core:chat.messages', 'm2')]);

        assert.equal(index.removeProvider('core:chat.messages'), true);
        assert.deepEqual(deltas.at(-1)?.removals, [documentKey('core:chat.messages', 'm1')]);
        assert.equal(index.hasWorkerDocuments, false);
        assert.equal(index.candidates(UNRESTRICTED_SCOPE).length, 0);
    });

    it('lists candidates in provider order, narrowed by scope', () => {
        const index = new SharedSearchIndex();
        index.setProviderEntries(provider({id: 'core:chat.conversations', order: 1}), [entry('a', 'Alpha'), entry('b', 'Beta')]);
        index.setProviderEntries(
            provider({id: 'core:settings.pages', moduleId: 'core:settings', order: 0}),
            [entry('s', 'Settings')]
        );

        assert.deepEqual(
            index.candidates(UNRESTRICTED_SCOPE).map(candidate => candidate.entry.id),
            ['s', 'a', 'b']
        );
        assert.deepEqual(
            index.candidates({pluginId: 'core', moduleId: 'core:chat'}).map(candidate => candidate.entry.id),
            ['a', 'b']
        );
        assert.deepEqual(index.candidates(UNRESTRICTED_SCOPE, 'worker'), []);
    });

    it('finds every document behind one entity', () => {
        const index = new SharedSearchIndex();
        index.setProviderEntries(provider({id: 'core:chat.conversations'}), [entry('a', 'Alpha')]);
        index.setProviderEntries(
            provider({id: 'core:chat.recents', order: 1}),
            [{...entry('r', 'Alpha'), entityKey: 'ai-convs/a'}]
        );

        assert.deepEqual(
            index.documentsForEntity('ai-convs/a', UNRESTRICTED_SCOPE).map(document => document.provider.id),
            ['core:chat.conversations', 'core:chat.recents']
        );
    });
});

/** The index reports dropped entries through `console.error`; tests assert on the count instead of printing them. */
function silenceErrors(): {readonly count: number; restore(): void} {
    const original = console.error;
    let count = 0;
    console.error = () => {
        count++;
    };
    return {
        get count() {
            return count;
        },
        restore: () => {
            console.error = original;
        }
    };
}
