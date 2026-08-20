import {createContext} from 'svelte';

export interface MenuListItemState {
    active: boolean;
    inset: boolean;
}

export interface MenuListContextValue {
    register: (element: HTMLElement) => number;
    unregister: (index: number) => void;
    setState: (index: number, state: MenuListItemState) => void;
}

const [getMenuListContext, setMenuListContext] = createContext<MenuListContextValue>();

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
