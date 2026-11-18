import {describe, it} from 'node:test';
import assert from 'node:assert/strict';
import {CommandSelection, isNavigationKey} from '$lib/components/ui/command/commandSelection.svelte.js';

/** A machine over the given selectable values; `fallbackMs` keeps the transaction test fast. */
function machine(values: readonly string[] = [], frozen = false, fallbackMs = 500): CommandSelection {
    return new CommandSelection({
        isFrozen: () => frozen,
        selectableValues: () => new Set(values),
        fallbackMs
    });
}

/** Lets the machine's microtask gesture window close, like the next task would. */
async function afterMicrotask(): Promise<void> {
    await new Promise<void>(resolve => queueMicrotask(resolve));
}

function key(key: string, ctrl = false): KeyboardEvent {
    return {key, ctrlKey: ctrl} as KeyboardEvent;
}

describe('CommandSelection', () => {
    it('accepts a value change inside a keyboard gesture as intent', () => {
        const selection = machine(['a', 'b']);

        selection.noteKeyboard();
        selection.acceptValueChange('b');

        assert.equal(selection.selection, 'b');
    });

    it('treats a value change after the gesture window as bookkeeping', async () => {
        const selection = machine(['a', 'b']);

        selection.noteKeyboard();
        await afterMicrotask();
        selection.acceptValueChange('a');

        assert.equal(selection.selection, '');
    });

    it('re-asserts the user’s row over bookkeeping while frozen, and drops a vanished one', () => {
        const selection = machine(['b'], true);

        selection.notePointer('b');
        selection.acceptValueChange('a');
        assert.equal(selection.value, 'b');

        const vanished = machine([], true);
        vanished.notePointer('gone');
        vanished.acceptValueChange('a');
        assert.equal(vanished.value, '');
    });

    it('leaves bookkeeping standing while unfrozen', () => {
        const selection = machine(['a', 'b']);

        selection.notePointer('b');
        // The binding writes the bookkeeping value; unfrozen, the machine
        // leaves it standing instead of fighting it.
        selection.value = 'a';
        selection.acceptValueChange('a');

        assert.equal(selection.value, 'a');
        assert.equal(selection.selection, 'b');
    });

    it('carries a selection through a transaction and closes it on delivery', () => {
        const selection = machine(['row']);

        selection.beginTransaction();
        selection.acceptValueChange('row');
        selection.endTransaction();
        selection.acceptValueChange('other');

        assert.equal(selection.selection, 'row');
    });

    it('closes a transaction that never delivers a selection', async () => {
        const selection = machine(['row'], false, 1);

        selection.beginTransaction();
        await new Promise(resolve => setTimeout(resolve, 5));
        selection.acceptValueChange('other');

        assert.equal(selection.selection, '');
    });

    it('drops a selection whose row vanished and re-asserts a surviving one', () => {
        const vanished = machine(['other']);
        vanished.notePointer('row');
        vanished.syncRows();
        assert.equal(vanished.selection, '');
        assert.equal(vanished.value, '');

        const survived = machine(['row']);
        survived.notePointer('row');
        // Bookkeeping (the binding) moved the value elsewhere; the rows
        // changed, not the user.
        survived.value = 'first';
        survived.syncRows();
        assert.equal(survived.value, 'row');
        assert.equal(survived.selection, 'row');
    });

    it('starts over on reset', () => {
        const selection = machine(['row']);
        selection.notePointer('row');
        selection.beginTransaction();

        selection.reset();

        assert.equal(selection.selection, '');
        assert.equal(selection.value, '');
        // The transaction is gone too: a late value change is bookkeeping.
        selection.acceptValueChange('row');
        assert.equal(selection.selection, '');
    });

    it('classifies navigation keys', () => {
        assert.equal(isNavigationKey(key('ArrowDown')), true);
        assert.equal(isNavigationKey(key('Home')), true);
        assert.equal(isNavigationKey(key('j', true)), true);
        assert.equal(isNavigationKey(key('j')), false);
        assert.equal(isNavigationKey(key('a', true)), false);
    });
});
