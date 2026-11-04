/**
 * Keyboard-aware chip removal for the composer's chip rows (`ToolChips`,
 * `FileChips`): removing a chip is not enough — focus has to land somewhere
 * sensible, or it falls back to `<body>` and keyboard users have to tab in
 * from the top of the page again.
 */
import {tick} from 'svelte';

/** What `removeChipAndMoveFocus` needs from the calling chip row. */
export interface ChipRemovalParams {
    /** Performs the removal. */
    remove: () => void;
    /**
     * Whether any chips remain after the removal — read from state, not the
     * DOM: the row may still be mid out-transition.
     */
    hasRemaining: () => boolean;
    /** The currently rendered chip elements, in order. */
    chipButtons: () => HTMLElement[];
    /**
     * Focuses the chip that took the removed one's place. The caller may
     * track its own active index here.
     */
    focusChip: (index: number, chips: HTMLElement[]) => void;
    /** Where focus goes when no chips are left. */
    focusFallback: () => void;
    /** The removed chip's position; focus lands on its successor. */
    index: number;
}

/**
 * Removes a chip and lands focus somewhere sensible: the chip that slid into
 * the removed slot, otherwise the preceding one, otherwise the fallback (the
 * textarea) once the row is empty.
 */
export async function removeChipAndMoveFocus(params: ChipRemovalParams): Promise<void> {
    params.remove();
    // tick() flushes the re-render; the extra frame lets the removed chip's
    // out-transition finish so it is out of the DOM before we pick the chip
    // to focus by index.
    await tick();
    await new Promise(requestAnimationFrame);

    if (!params.hasRemaining()) {
        params.focusFallback();
        return;
    }

    const chips = params.chipButtons();
    if (chips.length === 0) {
        params.focusFallback();
        return;
    }
    params.focusChip(Math.min(params.index, chips.length - 1), chips);
}

/**
 * Whether the pressed key should remove a chip from the keyboard. Enter/Space
 * is handled through this rather than the click handler so keyboard removal
 * can move focus onward while a mouse click returns to the textarea;
 * `preventDefault` stops the browser from firing a synthetic click afterwards.
 */
export function isChipRemoveKey(event: KeyboardEvent): boolean {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        return true;
    }
    return false;
}
