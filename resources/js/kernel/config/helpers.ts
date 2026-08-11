import type {z} from 'zod';
import type {HawkiConfigSchemas} from '$lib/kernel/extendableTypes.js';
import {getHawkiApp} from '$lib/legacy/legacy.js';

/**
 * Returns the parsed, validated config for a namespace.
 *
 * The result is cached after the first call, so repeated access is cheap.
 * Throws if the namespace has no registered schema — this is always a programming error, not a runtime condition.
 *
 * {@link createConfiguration} must have been called before this is used; otherwise
 * `currentConfig` is `null` and all fields will fall back to their Zod defaults.
 *
 * Calling without arguments returns the `'hawki-core'` config.
 *
 * @example
 * const { locale } = getConfig();               // hawki-core (default)
 * const ai = getConfig('my-feature').something; // specific namespace
 * @deprecated I would suggest using `useApp().config.get(...)` or `useConfig()` instead, as it is more explicit and avoids the need for a global function.
 */
export function getConfig(): z.infer<HawkiConfigSchemas['hawki-core']>;
/**
 * @deprecated I would suggest using `useApp().config.get(...)` or `useConfig()` instead, as it is more explicit and avoids the need for a global function.
 */
export function getConfig<N extends keyof HawkiConfigSchemas>(namespace: N): z.infer<HawkiConfigSchemas[N]>;
/**
 * @deprecated I would suggest using `useApp().config.get(...)` or `useConfig()` instead, as it is more explicit and avoids the need for a global function.
 */
export function getConfig<N extends keyof HawkiConfigSchemas>(namespace?: N): z.infer<HawkiConfigSchemas[N]> {
    return getHawkiApp().config.get((namespace ?? 'hawki-core') as N);
}
