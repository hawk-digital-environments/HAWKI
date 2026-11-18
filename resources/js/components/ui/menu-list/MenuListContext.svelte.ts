import type {Attachment} from 'svelte/attachments';
import {createStableContext} from '$lib/utils/stableContext.js';

export interface MenuListItemState {
    active: boolean;
    inset: boolean;
    /** Tints the hover highlight in the error hue while it sits on this row. */
    destructive?: boolean;
}

export interface MenuListContextValue {
    register: (element: HTMLElement) => number;
    unregister: (index: number) => void;
    setState: (index: number, state: MenuListItemState) => void;
}

const [getMenuListContext, setMenuListContext] = createStableContext<MenuListContextValue>('hawki.menu-list');

export function provideMenuList(context: MenuListContextValue): void {
    setMenuListContext(context);
}

/** Returns null when an item is intentionally rendered outside a menu list. */
export function useMenuList(): MenuListContextValue | null {
    try {
        return getMenuListContext() ?? null;
    } catch {
        return null;
    }
}

/**
 * For rows that need no active/inset state: an attachment that registers the row
 * with the enclosing MenuList, or null when there is no list around it. Pass
 * `destructive` for a row whose hover highlight takes the error tint.
 */
export function useMenuListRow(options: {destructive?: boolean} = {}): Attachment<HTMLElement> | null {
    const list = useMenuList();
    if (!list) return null;
    return (element) => {
        const index = list.register(element);
        if (options.destructive) list.setState(index, {active: false, inset: false, destructive: true});
        return () => list.unregister(index);
    };
}
