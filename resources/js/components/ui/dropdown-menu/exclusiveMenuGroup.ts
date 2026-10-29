import {getContext, setContext} from 'svelte';

/**
 * Lets a set of `DropdownMenu`s behave like one: opening a menu of the group closes
 * whichever other one is open. Opening a menu straight from another one's trigger
 * otherwise leaves both open, since bits-ui only dismisses the topmost layer.
 */
export interface ExclusiveMenuGroup {
    /** Marks `close` as the open menu, closing the one open before. */
    claim(close: () => void): void;

    /** Forgets `close` again, if it is still the open menu. */
    release(close: () => void): void;
}

const exclusiveMenuGroupKey = Symbol.for('hawki.ui.exclusive-menu-group');

/**
 * Provides an {@link ExclusiveMenuGroup} for the current component subtree: every
 * `DropdownMenu` rendered inside joins it. Pass `null` to opt a subtree out again.
 */
export function provideExclusiveMenuGroup(group: ExclusiveMenuGroup | null = createExclusiveMenuGroup()): ExclusiveMenuGroup | null {
    return setContext(exclusiveMenuGroupKey, group);
}

/** The group an ancestor provided, or null when the menu stands on its own. */
export function useExclusiveMenuGroup(): ExclusiveMenuGroup | null {
    return getContext<ExclusiveMenuGroup | null | undefined>(exclusiveMenuGroupKey) ?? null;
}

function createExclusiveMenuGroup(): ExclusiveMenuGroup {
    let current: (() => void) | null = null;
    return {
        claim(close) {
            const previous = current;
            current = close;
            if (previous && previous !== close) {
                previous();
            }
        },
        release(close) {
            if (current === close) {
                current = null;
            }
        }
    };
}
