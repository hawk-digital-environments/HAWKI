import { createStableContext } from '$lib/utils/stableContext.js';
import { isApple } from '$lib/utils/platform.js';

/**
 * Platform-agnostic description of a keyboard shortcut. `ctrl` denotes the
 * platform's primary shortcut modifier: Command (⌘) on Apple platforms —
 * which the DOM reports as the `meta` flag — and Ctrl everywhere else.
 */
export interface Shortcut {
    /** The main key, as reported by `KeyboardEvent.key` (case-insensitive). */
    key: string;
    /** Require the platform's primary shortcut modifier (⌘/Ctrl). */
    ctrl?: boolean;
    /** Require Shift to be held. */
    shift?: boolean;
    /** Require Alt (⌥) to be held. */
    alt?: boolean;
}

/**
 * Reactive source of truth for keyboard modifier state, shared with every
 * component in the subtree via {@link useKeyboard}. Owns the app's single
 * set of window-level key listeners and centralizes the platform difference
 * (⌘ vs Ctrl) behind shortcut matching and label helpers, so callers never
 * branch on {@link isApple} themselves.
 */
export class KeyboardState {
    /** Modifiers held right now, mirrored from the last key event. */
    public readonly modifiers = $state({ ctrl: false, shift: false, alt: false, meta: false });

    public constructor() {
        if (typeof window === 'undefined') {
            return; // Outside the browser (e.g. tests without a DOM).
        }

        const track = (event: KeyboardEvent) => {
            this.modifiers.ctrl = event.ctrlKey;
            this.modifiers.shift = event.shiftKey;
            this.modifiers.alt = event.altKey;
            this.modifiers.meta = event.metaKey;
        };
        // A keyup fired in another window never reaches us, so blur resets.
        const reset = () => {
            this.modifiers.ctrl = false;
            this.modifiers.shift = false;
            this.modifiers.alt = false;
            this.modifiers.meta = false;
        };

        window.addEventListener('keydown', track);
        window.addEventListener('keyup', track);
        window.addEventListener('blur', reset);
    }

    /** Whether the platform's primary shortcut modifier (⌘/Ctrl) is held. */
    public get shortcutModifierHeld(): boolean {
        return isApple ? this.modifiers.meta : this.modifiers.ctrl;
    }

    /** Whether the event is exactly the shortcut: same key, no missing and no extra modifiers. */
    public matches(event: KeyboardEvent, shortcut: Shortcut): boolean {
        if (event.key.toLowerCase() !== shortcut.key.toLowerCase()) {
            return false;
        }
        return this.sameModifiers(
            { ctrl: event.ctrlKey, shift: event.shiftKey, alt: event.altKey, meta: event.metaKey },
            shortcut
        );
    }

    /** Whether the modifiers currently held are exactly the shortcut's; extra ones fail too. */
    public modifiersHeld(shortcut: Shortcut): boolean {
        return this.sameModifiers(this.modifiers, shortcut);
    }

    /** Canonical display label: `⇧⌘O` on Apple platforms, `Ctrl Shift O` elsewhere. */
    public label(shortcut: Shortcut, keyLabel?: string): string {
        return this.labelParts(shortcut, keyLabel).join(isApple ? '' : ' ');
    }

    /** Label parts for callers that format the combination themselves (e.g. join with `' + '`). */
    public labelParts(shortcut: Shortcut, keyLabel?: string): string[] {
        const key = keyLabel ?? displayKey(shortcut.key);
        if (isApple) {
            return [
                ...(shortcut.alt ? ['⌥'] : []),
                ...(shortcut.shift ? ['⇧'] : []),
                ...(shortcut.ctrl ? ['⌘'] : []),
                key
            ];
        }
        return [
            ...(shortcut.ctrl ? ['Ctrl'] : []),
            ...(shortcut.alt ? ['Alt'] : []),
            ...(shortcut.shift ? ['Shift'] : []),
            key
        ];
    }

    /** Value for the shortcut's `aria-keyshortcuts` attribute (`Meta+K` / `Control+K`). */
    public aria(shortcut: Shortcut): string {
        return [
            ...(shortcut.ctrl ? [isApple ? 'Meta' : 'Control'] : []),
            ...(shortcut.alt ? ['Alt'] : []),
            ...(shortcut.shift ? ['Shift'] : []),
            displayKey(shortcut.key)
        ].join('+');
    }

    private sameModifiers(
        held: { ctrl: boolean; shift: boolean; alt: boolean; meta: boolean },
        shortcut: Shortcut
    ): boolean {
        const wantCtrl = !!shortcut.ctrl && !isApple;
        const wantMeta = !!shortcut.ctrl && isApple;
        return (
            held.ctrl === wantCtrl &&
            held.meta === wantMeta &&
            held.shift === !!shortcut.shift &&
            held.alt === !!shortcut.alt
        );
    }
}

function displayKey(key: string): string {
    return key.length === 1 ? key.toUpperCase() : key;
}

const [getKeyboardContext, setKeyboardContext] = createStableContext<KeyboardState>('hawki.keyboard');

/**
 * Creates the {@link KeyboardState} and provides it to the component
 * subtree. Call once per Svelte root, near the root of the layout.
 */
export function createKeyboardContext(): KeyboardState {
    const keyboard = new KeyboardState();
    setKeyboardContext(keyboard);
    return keyboard;
}

/** Hook that gives components access to the shared keyboard state. */
export function useKeyboard(): KeyboardState {
    return getKeyboardContext();
}
