import type {Attachment} from 'svelte/attachments';
import {createHmrSafeContext} from '$lib/utils/hmrSafeContext.js';

export interface MenuListItemState {
    active: boolean;
    inset: boolean;
}

export interface MenuListContextValue {
    register: (element: HTMLElement) => number;
    unregister: (index: number) => void;
    setState: (index: number, state: MenuListItemState) => void;
}

const [getMenuListContext, setMenuListContext] = createHmrSafeContext<MenuListContextValue>('hawki.menu-list');

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
 * with the enclosing MenuList, or null when there is no list around it.
 */
export function useMenuListRow(): Attachment<HTMLElement> | null {
    const list = useMenuList();
    if (!list) return null;
    return (element) => {
        const index = list.register(element);
        return () => list.unregister(index);
    };
}
