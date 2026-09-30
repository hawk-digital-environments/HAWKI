import {strict as assert} from 'node:assert';
import test, {describe} from 'node:test';
import {
    appendReadyGroups,
    applyRowLimits,
    dedupeByEntity,
    rankGroups,
    refreshFrozenGroups,
    type RankingCandidate,
    type RankingGroup
} from '$lib/kernel/search/sessionPublication.js';
import {SEARCH_ROWS_PER_GROUP, SEARCH_ROWS_TOTAL, type SearchGroupView, type SearchRow} from '$lib/kernel/search/types.js';

function row(id: string, groupId: string, kind: 'static' | 'dynamic' = 'static', entityKey = `entity/${id}`): SearchRow {
    return {
        id,
        entityKey,
        title: id,
        providerId: `${groupId}.provider`,
        groupId,
        kind,
        available: true,
        onSelect: () => undefined
    };
}

function candidate(value: SearchRow, overrides: Partial<RankingCandidate> = {}): RankingCandidate {
    return {
        row: value,
        score: 0,
        recency: 0,
        providerOrder: 0,
        entryOrder: 0,
        preferStatic: value.kind === 'static',
        ...overrides
    };
}

const groups: RankingGroup[] = [
    {id: 'core:chat.conversations', label: 'Conversations', kind: 'static', order: 0},
    {id: 'core:chat.messages', label: 'Messages', kind: 'static', order: 1},
    {id: 'core:chat.remote', label: 'Server', kind: 'dynamic', order: 2}
];

function groupView(id: string, count: number): SearchGroupView {
    return {
        id,
        label: id,
        kind: 'static',
        items: Array.from({length: count}, (_, index) => row(`${id}-${index}`, id))
    };
}

describe('dedupeByEntity', () => {
    test('prefers the live static copy over a remote copy of the same entity', () => {
        const shared = 'ai-convs/x';
        const remote = candidate(row('remote', 'core:chat.remote', 'dynamic', shared), {score: 9});
        const local = candidate(row('local', 'core:chat.conversations', 'static', shared), {score: 1});

        const [kept] = dedupeByEntity([remote, local]);

        assert.equal(kept.row.id, 'local');
        assert.equal(kept.row.groupId, 'core:chat.conversations');
    });

    test('falls back to relevance, then registration order, between equal kinds', () => {
        const shared = 'ai-convs/x';
        const later = candidate(row('later', 'core:chat.conversations', 'static', shared), {score: 2, providerOrder: 5});
        const earlier = candidate(row('earlier', 'core:chat.conversations', 'static', shared), {score: 2, providerOrder: 1});

        assert.equal(dedupeByEntity([later, earlier])[0].row.id, 'earlier');
        assert.equal(dedupeByEntity([{...later, score: 3}, earlier])[0].row.id, 'later');
    });
});

describe('rankGroups', () => {
    test('orders groups by their best row and rows by score', () => {
        const ranked = rankGroups([
            candidate(row('weak', 'core:chat.conversations'), {score: 1}),
            candidate(row('strong', 'core:chat.messages'), {score: 8}),
            candidate(row('middle', 'core:chat.messages'), {score: 4})
        ], groups);

        assert.deepEqual(ranked.map(group => group.id), ['core:chat.messages', 'core:chat.conversations']);
        assert.deepEqual(ranked[0].items.map(item => item.id), ['strong', 'middle']);
    });

    test('breaks ties by group and provider registration order', () => {
        const ranked = rankGroups([
            candidate(row('second', 'core:chat.messages'), {score: 3, providerOrder: 4}),
            candidate(row('first', 'core:chat.conversations'), {score: 3, providerOrder: 9}),
            candidate(row('sibling', 'core:chat.messages'), {score: 3, providerOrder: 2})
        ], groups);

        assert.deepEqual(ranked.map(group => group.id), ['core:chat.conversations', 'core:chat.messages']);
        assert.deepEqual(ranked[1].items.map(item => item.id), ['sibling', 'second']);
    });

    test('keeps a remote non-match eligible at score zero, behind every scored row', () => {
        const ranked = rankGroups([
            candidate(row('unrelated-title', 'core:chat.remote', 'dynamic'), {score: 0, entryOrder: 0}),
            candidate(row('matched', 'core:chat.conversations'), {score: 2})
        ], groups);

        assert.deepEqual(ranked.map(group => group.id), ['core:chat.conversations', 'core:chat.remote']);
        assert.equal(ranked[1].items.length, 1);
    });
});

describe('applyRowLimits', () => {
    test('applies the per-group cap before the total cap', () => {
        const limited = applyRowLimits([groupView('a', 12), groupView('b', 12), groupView('c', 12)]);

        assert.deepEqual(limited.map(group => group.items.length), [SEARCH_ROWS_PER_GROUP, SEARCH_ROWS_PER_GROUP, SEARCH_ROWS_PER_GROUP]);
    });

    test('never exceeds the total, dropping the lowest ranked groups', () => {
        const limited = applyRowLimits(Array.from({length: 6}, (_, index) => groupView(`g${index}`, 5)));
        const total = limited.reduce((sum, group) => sum + group.items.length, 0);

        assert.equal(total, SEARCH_ROWS_TOTAL);
        assert.equal(limited.length, 4);
    });
});

describe('appendReadyGroups', () => {
    test('appends a newly ready group without touching the frozen ones', () => {
        const frozen = [groupView('a', 3)];

        const next = appendReadyGroups(frozen, [groupView('b', 2), groupView('a', 5)]);

        assert.deepEqual(next.map(group => group.id), ['a', 'b']);
        assert.equal(next[0].items.length, 3, 'the visible group must not grow');
        assert.equal(next[0].items[0], frozen[0].items[0], 'existing rows keep their identity');
    });

    test('stops at the total budget and never evicts a frozen row', () => {
        const frozen = [groupView('a', 5), groupView('b', 5), groupView('c', 5), groupView('d', 3)];

        const next = appendReadyGroups(frozen, [groupView('e', 5)]);

        assert.equal(next.at(-1)!.id, 'e');
        assert.equal(next.at(-1)!.items.length, 2, 'only the remaining budget is used');
        assert.equal(next.reduce((sum, group) => sum + group.items.length, 0), SEARCH_ROWS_TOTAL);
    });

    test('adds nothing once the frozen list is full', () => {
        const frozen = [groupView('a', 5), groupView('b', 5), groupView('c', 5), groupView('d', 5)];

        assert.deepEqual(appendReadyGroups(frozen, [groupView('e', 5)]).map(group => group.id), ['a', 'b', 'c', 'd']);
    });
});

describe('refreshFrozenGroups', () => {
    test('marks a vanished row unavailable in its slot and re-resolves the heading', () => {
        const frozen = [groupView('a', 3)];

        const next = refreshFrozenGroups(frozen, {
            isAvailable: candidateRow => candidateRow.id !== 'a-1',
            label: id => `${id} translated`
        });

        assert.equal(next[0].label, 'a translated');
        assert.deepEqual(next[0].items.map(item => item.available), [true, false, true]);
        assert.deepEqual(next[0].items.map(item => item.id), ['a-0', 'a-1', 'a-2'], 'nothing moved');
    });
});
