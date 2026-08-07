import {useApp} from '$lib/app/hooks/useApp.svelte.js';
import type {z} from 'zod';
import type {HawkiConfigSchemas} from '$lib/kernel/extendableTypes.js';

export function useConfig(): z.infer<HawkiConfigSchemas['hawki-core']>;
export function useConfig<N extends keyof HawkiConfigSchemas>(namespace: N): z.infer<HawkiConfigSchemas[N]>;
export function useConfig<N extends keyof HawkiConfigSchemas>(namespace?: N): z.infer<HawkiConfigSchemas[N]> {
    const app = useApp();
    return $derived.by(() => app.config.get(namespace as N));
}
