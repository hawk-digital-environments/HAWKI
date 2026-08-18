import {useApp} from '$lib/app/hooks/useApp.svelte.js';
import type {z} from 'zod';
import type {HawkiConfigSchemas} from '$lib/kernel/extendableTypes.js';

/**
 * Hook that gives components access to server-provided, namespaced app
 * configuration (`app.config.get(namespace)`).
 *
 * Config is fetched once from the API and split into namespaces so that each
 * feature module can own and validate its own slice (e.g. `'hawki-core'` for
 * core settings such as locale/transfer/security, or a plugin-specific
 * namespace it registers itself — see `HawkiConfigSchemas` in
 * `kernel/extendableTypes.ts` for the full list of registered namespaces).
 * Each namespace is parsed against its registered Zod schema on first
 * access and the parsed result is cached, so repeated reads are cheap and
 * always return validated data — never raw/unvalidated JSON.
 *
 * Call without an argument to get the default `'hawki-core'` namespace, or
 * pass a namespace key to get that namespace's config; the return type is
 * inferred from the matching Zod schema in either case.
 *
 * The returned value is a plain (non-reactive) object: config is fetched once
 * during bootstrap and parsed/cached per namespace, so it does not change
 * during a session — there is nothing to track reactively.
 *
 * Throws if no schema is registered for the requested namespace.
 *
 * @param namespace The config namespace to read (defaults to `'hawki-core'`).
 *
 * @example
 * ```svelte
 * <script lang="ts">
 *     import {useConfig} from '$lib/app/hooks/useConfig.svelte.js';
 *
 *     const coreConfig = useConfig(); // same as useConfig('hawki-core')
 * </script>
 *
 * <p>Default locale: {coreConfig.locale.default}</p>
 * ```
 */
export function useConfig(): z.infer<HawkiConfigSchemas['hawki-core']>;
export function useConfig<N extends keyof HawkiConfigSchemas>(namespace: N): z.infer<HawkiConfigSchemas[N]>;
export function useConfig<N extends keyof HawkiConfigSchemas>(namespace?: N): z.infer<HawkiConfigSchemas[N]> {
    const app = useApp();
    return app.config.get(namespace as N);
}
