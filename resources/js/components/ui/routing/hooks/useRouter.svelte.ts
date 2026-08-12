import {createContext, getContext} from 'svelte';
import {getRouterContextName, type RouterHandle} from '$lib/components/ui/routing/logistics/router.svelte.js';

const [getDefaultRouterName, setDefaultRouterName] = createContext<string>();

export function useRouter(name?: string): RouterHandle {
    try {
        name = name ?? getDefaultRouterName();
    } catch (error) {
        name = name ?? 'app';
    }
    const routerHandle = getContext<RouterHandle>(getRouterContextName(name));
    if (!routerHandle) {
        throw new Error(`Router context not found for name: ${name ?? 'app'}`);
    }
    return routerHandle;
}

export function provideDefaultRouterName(name: string): void {
    setDefaultRouterName(name);
}
