import assert from 'node:assert/strict';
import {test} from 'node:test';
import {
    createShareSelection,
    type ShareBaselineEntry,
    type ShareCandidate
} from '../../../resources/js/plugins/assistants/modules/dashboard/components/shareDialog/shareSelection.svelte.js';

function share(userId: number, canEdit = false): ShareBaselineEntry {
    return {userId, displayName: `User ${userId}`, username: `user${userId}`, canEdit, avatar: null};
}

function candidate(userId: number): ShareCandidate {
    return {userId, displayName: `User ${userId}`, username: `user${userId}`, avatar: null};
}

test('adopting the baseline makes the selection clean', () => {
    const selection = createShareSelection();

    selection.adopt([share(2), share(3, true)]);

    assert.equal(selection.dirty, false);
    assert.deepEqual(
        selection.payload.map((entry) => [entry.userId, entry.canEdit]),
        [[2, false], [3, true]]
    );
});

test('toggling a loaded share off and on again returns to clean', () => {
    const selection = createShareSelection();
    selection.adopt([share(2)]);

    selection.toggle(2);
    assert.equal(selection.dirty, true);

    selection.toggle(2);
    assert.equal(selection.dirty, false);
});

test('flipping the edit grant marks the selection dirty', () => {
    const selection = createShareSelection();
    selection.adopt([share(2, false)]);

    selection.find(2)!.canEdit = true;

    assert.equal(selection.dirty, true);
    assert.deepEqual(selection.payload, [{userId: 2, canEdit: true}]);
});

test('seeding search results adds unselected persons without changing the payload', () => {
    const selection = createShareSelection();
    selection.adopt([share(2)]);

    selection.seed([candidate(2), candidate(5)]);

    assert.equal(selection.dirty, false);
    assert.equal(selection.find(5)?.selected, false);
    assert.deepEqual(selection.payload, [{userId: 2, canEdit: false}]);
});

test('selecting a seeded candidate produces the share payload in full', () => {
    const selection = createShareSelection();
    selection.adopt([share(2, true)]);
    selection.seed([candidate(5), candidate(7)]);

    selection.toggle(5);
    selection.toggle(7);

    assert.equal(selection.dirty, true);
    assert.deepEqual(
        selection.payload.map((entry) => [entry.userId, entry.canEdit]),
        [[2, true], [5, false], [7, false]]
    );
});

test('an empty baseline with no picks is clean; deselecting everyone syncs an empty list', () => {
    const selection = createShareSelection();
    selection.adopt([share(2), share(3)]);

    selection.toggle(2);
    selection.toggle(3);

    assert.equal(selection.dirty, true);
    assert.deepEqual(selection.payload, []);
});

test('adopt after save resets the baseline to the server answer', () => {
    const selection = createShareSelection();
    selection.adopt([share(2, false)]);
    selection.toggle(2);
    selection.seed([candidate(4)]);
    selection.toggle(4);

    // The sync's answer: 2 is gone, 4 is shared with the edit grant.
    selection.adopt([share(4, true)]);

    assert.equal(selection.dirty, false);
    assert.deepEqual(selection.payload, [{userId: 4, canEdit: true}]);
});
