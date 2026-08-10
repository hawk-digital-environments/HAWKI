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
 * The returned value is wrapped in `$derived.by(...)`, so it is a reactive
 * Svelte 5 value — reading it inside a component template/derivation tracks
 * it like any other rune-based reactive value. In practice, config is loaded
 * once per page and cached, so it will not change during a session unless
 * the underlying `ConfigurationExtension` cache is explicitly invalidated.
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
    return $derived.by(() => app.config.get(namespace as N));
}
