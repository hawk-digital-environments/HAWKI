import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { KeyboardState } from '$lib/components/ui/keyboard/KeyboardState.svelte.js';

/**
 * Tests run in Node, where `navigator` is undefined and `isApple` is `false`,
 * so these cover the non-Apple branch; the Apple branch differs only in the
 * `ctrl`/`meta` flag and the label glyphs.
 */
const keyboard = new KeyboardState();

function event(
    key: string,
    modifiers: { ctrl?: boolean; shift?: boolean; alt?: boolean; meta?: boolean } = {}
): KeyboardEvent {
    return {
        key,
        ctrlKey: modifiers.ctrl ?? false,
        shiftKey: modifiers.shift ?? false,
        altKey: modifiers.alt ?? false,
        metaKey: modifiers.meta ?? false
    } as KeyboardEvent;
}

describe('KeyboardState', () => {
    describe('matches', () => {
        it('matches the exact key and modifiers', () => {
            const shortcut = { key: 'o', ctrl: true, shift: true };
            assert.ok(keyboard.matches(event('o', { ctrl: true, shift: true }), shortcut));
            assert.ok(keyboard.matches(event('O', { ctrl: true, shift: true }), shortcut));
        });

        it('rejects missing, extra and wrong keys', () => {
            const shortcut = { key: 'o', ctrl: true };
            assert.ok(!keyboard.matches(event('o'), shortcut));
            assert.ok(!keyboard.matches(event('o', { ctrl: true, alt: true }), shortcut));
            assert.ok(!keyboard.matches(event('k', { ctrl: true }), shortcut));
        });
    });

    describe('modifiersHeld', () => {
        it('tracks held modifiers and rejects extras', () => {
            keyboard.modifiers.ctrl = true;
            assert.ok(keyboard.modifiersHeld({ key: 'k', ctrl: true }));
            assert.ok(!keyboard.modifiersHeld({ key: 'k', ctrl: true, shift: true }));
            keyboard.modifiers.ctrl = false;
            assert.ok(!keyboard.modifiersHeld({ key: 'k', ctrl: true }));
        });

        it('exposes the shortcut modifier as held while ctrl is down', () => {
            keyboard.modifiers.ctrl = true;
            assert.ok(keyboard.shortcutModifierHeld);
            keyboard.modifiers.ctrl = false;
            assert.ok(!keyboard.shortcutModifierHeld);
        });
    });

    describe('labels', () => {
        it('labels shortcuts with words and single-letter keys upper-cased', () => {
            assert.equal(keyboard.label({ key: 'o', ctrl: true, shift: true }), 'Ctrl Shift O');
            assert.equal(keyboard.label({ key: 'k', ctrl: true }), 'Ctrl K');
            assert.equal(keyboard.label({ key: 'Escape' }), 'Escape');
            assert.equal(keyboard.label({ key: 'Escape' }, 'Esc'), 'Esc');
            assert.equal(keyboard.label({ key: 'a' }, 'any text'), 'any text');
        });

        it('gives parts for callers that format themselves', () => {
            assert.deepEqual(keyboard.labelParts({ key: 'a', ctrl: true, shift: true }), ['Ctrl', 'Shift', 'A']);
        });

        it('builds aria-keyshortcuts values', () => {
            assert.equal(keyboard.aria({ key: 'k', ctrl: true }), 'Control+K');
            assert.equal(keyboard.aria({ key: 'o', ctrl: true, shift: true }), 'Control+Shift+O');
        });
    });
});
