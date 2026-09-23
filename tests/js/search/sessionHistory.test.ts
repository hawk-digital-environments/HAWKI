import {strict as assert} from 'node:assert';
import test, {describe} from 'node:test';
import {SearchRecentsStore} from '$lib/kernel/search/sessionHistory.js';
import {SEARCH_RECENTS_LIMIT} from '$lib/kernel/search/types.js';
import {MemoryStorage} from './harness.js';

function clock(): () => number {
    let now = 1_000;
    return () => (now += 10);
}

describe('SearchRecentsStore', () => {
    test('keeps selections distinct and most recent first', () => {
        const store = new SearchRecentsStore(new MemoryStorage(), 'user-1', clock());

        store.record('ai-convs/a');
        store.record('ai-convs/b');
        store.record('ai-convs/a');

        assert.deepEqual(store.entries.map(selection => selection.entityKey), ['ai-convs/a', 'ai-convs/b']);
    });

    test('caps the history at the documented limit', () => {
        const store = new SearchRecentsStore(new MemoryStorage(), 'user-1', clock());

        for (let index = 0; index < SEARCH_RECENTS_LIMIT + 5; index++) {
            store.record(`ai-convs/${index}`);
        }

        assert.equal(store.entries.length, SEARCH_RECENTS_LIMIT);
        assert.equal(store.entries[0].entityKey, `ai-convs/${SEARCH_RECENTS_LIMIT + 4}`);
    });

    test('persists nothing but identifiers and timestamps', () => {
        const storage = new MemoryStorage();
        const store = new SearchRecentsStore(storage, 'user-1', clock());

        store.record('ai-convs/a');

        const persisted = JSON.parse(storage.getItem('hawki.search.recents.user-1')!);
        assert.deepEqual(Object.keys(persisted[0]).sort(), ['at', 'entityKey']);
    });

    test('separates the history of two identities and restores it on the way back', () => {
        const storage = new MemoryStorage();
        const store = new SearchRecentsStore(storage, 'user-1', clock());
        store.record('ai-convs/a');

        store.useIdentity('user-2');
        assert.deepEqual(store.entries, []);
        store.record('ai-convs/b');

        store.useIdentity('user-1');
        assert.deepEqual(store.entries.map(selection => selection.entityKey), ['ai-convs/a']);
    });

    test('persists nothing without an identity', () => {
        const storage = new MemoryStorage();
        const store = new SearchRecentsStore(storage, null, clock());

        store.record('ai-convs/a');

        assert.equal(storage.items.size, 0);
        assert.deepEqual(store.entries.map(selection => selection.entityKey), ['ai-convs/a']);
    });

    test('recovers from unreadable stored data instead of throwing', () => {
        const storage = new MemoryStorage();
        storage.setItem('hawki.search.recents.user-1', '{not json');

        assert.deepEqual(new SearchRecentsStore(storage, 'user-1', clock()).entries, []);

        storage.setItem('hawki.search.recents.user-1', JSON.stringify([{entityKey: 'a'}, {at: 1}, {entityKey: 'b', at: 2}]));
        assert.deepEqual(
            new SearchRecentsStore(storage, 'user-1', clock()).entries.map(selection => selection.entityKey),
            ['b']
        );
    });
});
