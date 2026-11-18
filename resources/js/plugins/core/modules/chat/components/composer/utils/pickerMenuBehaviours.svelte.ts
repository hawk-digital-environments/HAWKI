/**
 * Shared behaviour of the composer's three picker menus — the model picker,
 * the tool menu and the assistant menu. All three are `DropdownMenu`s with a
 * search field and a detail view, and their menus behaved through the same
 * copy-pasted effects and keyboard handlers. `usePickerMenuBehaviours`
 * centralises that machinery; each picker supplies the parts that differ:
 *
 * - what counts as "the current row" (the picked model, the tagged assistant,
 *   the last-activated tool) via {@link PickerMenuOptions.initialRow},
 * - what transient state to reset when the menu closes
 *   ({@link PickerMenuOptions.onClose}), and
 * - whether a detail view is showing and how it is cleared.
 *
 * The reveal effect is opt-in through `initialRow` (the tool menu keeps focus
 * in its search field instead), and the command-mode reset only runs when a
 * `commandQuery` getter is given. Call during component initialization — the
 * effects it registers are tied to the caller's lifetime, like any `$effect`.
 */
import {untrack} from 'svelte';
import {revealMenuRow} from './revealMenuRow.js';

/** How long a closing menu keeps its detail view around, so the closing animation does not flicker back to the list. */
const DETAIL_KEEP_OPEN_MS = 200;

/** Options for {@link usePickerMenuBehaviours} — see the module overview. */
export interface PickerMenuOptions {
    /** Reads the dropdown's open state. */
    isOpen: () => boolean;
    /**
     * Resets transient state once the menu has closed: the search query, plus
     * whatever picker-specific state exists (e.g. the model picker's preview).
     */
    onClose: () => void;
    /** Whether the detail view is currently showing. */
    hasDetail: () => boolean;
    /** Clears the detail selection — called after the keep-open delay. */
    clearDetail: () => void;
    /**
     * The row the menu should reveal and land ArrowDown on when it opens —
     * the picked model, the tagged assistant, … Returns null when there is
     * nothing to highlight. Omit for menus that keep focus in their search
     * field (no reveal effect is registered then).
     */
    initialRow?: (container: ParentNode) => HTMLElement | null;
    /** Selector of the menu content element — the reveal scroll container. */
    contentSelector?: string;
    /**
     * Skips the reveal, e.g. while a command menu owns the highlight. Read
     * untracked, so the reveal effect stays keyed on the open state only.
     */
    skipReveal?: () => boolean;
    /**
     * The typed command query, or null while no command is active. Given a
     * getter, the command highlight resets whenever the query really changes.
     */
    commandQuery?: () => string | null;
    /** Resets the command-menu highlight (see `commandQuery`). */
    resetCommandHighlight?: () => void;
}

/** What {@link usePickerMenuBehaviours} hands back to the picker. */
export interface PickerMenuBehaviours {
    /**
     * Keyboard handling ahead of bits-ui's own, for the dropdown content's
     * capture phase:
     * - typing while a row has focus goes to the search field instead of
     *   bits-ui's typeahead, so the list still filters as you type;
     * - arrowing out of the search field lands on the picker's initial row
     *   rather than the first row; falls through when that row is filtered out.
     */
    onContentKeydownCapture: (event: KeyboardEvent) => void;
}

/**
 * Registers the shared picker-menu effects (see the module overview) and
 * returns the shared content keyboard handler.
 */
export function usePickerMenuBehaviours(options: PickerMenuOptions): PickerMenuBehaviours {
    // Reset transient state once the menu has closed.
    $effect(() => {
        if (!options.isOpen()) {
            options.onClose();
        }
    });

    // When the menu closes with a detail view showing, keep the detail for a
    // short delay to avoid flickering back to the list mid-close.
    $effect(() => {
        if (!options.isOpen() && options.hasDetail()) {
            const t = setTimeout(() => options.clearDetail(), DETAIL_KEEP_OPEN_MS);
            return () => clearTimeout(t);
        }
    });

    // The list opens scrolled minimally to the initial row, while focus stays
    // in the search field; ArrowDown then lands on that row. Two frames, so
    // the list has laid out after `MenuSearchField` mounts.
    $effect(() => {
        const initialRow = options.initialRow;
        const contentSelector = options.contentSelector;
        if (!initialRow || !contentSelector) {
            return;
        }
        if (!options.isOpen() || (options.skipReveal && untrack(options.skipReveal))) {
            return;
        }
        let frame = requestAnimationFrame(() => {
            frame = requestAnimationFrame(() => {
                const row = initialRow(document.querySelector(contentSelector) ?? document);
                if (!row) {
                    return;
                }
                revealMenuRow(row, contentSelector);
            });
        });
        return () => cancelAnimationFrame(frame);
    });

    // In command mode the highlight starts over whenever the typed query
    // changes the rows. A frame later, so the content has mounted and the
    // filtered rows have rendered. Keyed on the query string, not the command
    // object, so only real filtering resets it.
    $effect(() => {
        const query = options.commandQuery?.() ?? null;
        if (!options.isOpen() || query === null || !options.resetCommandHighlight) {
            return;
        }
        const frame = requestAnimationFrame(() => options.resetCommandHighlight?.());
        return () => cancelAnimationFrame(frame);
    });

    return {
        onContentKeydownCapture(event: KeyboardEvent): void {
            const content = event.currentTarget as HTMLElement;
            const inSearch = event.target instanceof HTMLInputElement;
            if (!inSearch && event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey && event.key !== ' ') {
                // Hidden fields (detail view showing) cannot take focus or input.
                const search = content.querySelector<HTMLInputElement>('.menu-search:not([hidden]) input');
                if (search) {
                    // Focus moves before the key's default action, so the character lands in the field.
                    event.stopPropagation();
                    search.focus();
                }
                return;
            }
            if (event.key !== 'ArrowDown' || !inSearch) {
                return;
            }
            const row = options.initialRow?.(content) ?? null;
            if (!row) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            row.focus();
        }
    };
}
