import {useApp} from './useApp.svelte.js';

export function useCan() {
    const app = useApp();
    return (permission: string) => app.can(permission);
}
