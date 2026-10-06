import { useApp } from '$lib/app/hooks/useApp.svelte.js';
import type { z } from 'zod';
import type { HawkiUserSettingsSchemas } from '$lib/kernel/extendableTypes.js';

/**
 * Hook that gives components access to per-user settings from the
 * `user-settings` JSON:API resource (`app.userSettings.get(namespace)`).
 *
 * User settings are fetched during the bootstrapper's `preparation` stage
 * and split into namespaces so each feature module owns just the settings it
 * declares (e.g. `'hawki-core'` for locale/theme/timezone). Unlike
 * {@link useConfig} (global app configuration), user settings are **per-user**
 * (or per-session for guests) and **writable** from the frontend via
 * `app.userSettings.save(namespace, publicKey, partial)`.
 *
 * Each namespace resource is parsed against its registered Zod schema on
 * first access and the parsed result is cached, so repeated reads are cheap
 * and always return validated data — never raw/validated JSON.
 *
 * Settings state is **reactive** — when `UserSettingsExtension.refresh()` is
 * called (e.g. after an authentication transition), already-returned namespace
 * objects are updated in place, so existing component references observe the
 * new values without a re-fetch at the hook level.
 *
 * Call without an argument to get the default `'hawki-core'` namespace, or
 * pass a namespace key to get that namespace's settings; the return type is
 * inferred from the matching Zod schema in either case.
 *
 * **Writing:** call `app.userSettings.save('hawki-core', 'core', {theme: 'dark'})`
 * directly — there is no write-method on the hook. The extension validates the
 * merge before sending the PATCH and updates the parsed cache so the reactive
 * object you hold updates automatically after a successful save.
 *
 * Throws if no schema is registered for the requested namespace (always a
 * programming error).
 *
 * @param namespace The user-settings namespace to read (defaults to `'hawki-core'`).
 *
 * @example
 * ```svelte
 * <script lang="ts">
 *     import {useUserSettings} from '$lib/app/hooks/useUserSettings.svelte.js';
 *
 *     const settings = useUserSettings(); // hawki-core (default)
 *     const userTheme = $derived(settings.core.theme);
 * </script>
 * ```
 */
export function useUserSettings(): z.infer<HawkiUserSettingsSchemas['hawki-core']>;
export function useUserSettings<N extends keyof HawkiUserSettingsSchemas>(
    namespace: N
): z.infer<HawkiUserSettingsSchemas[N]>;
export function useUserSettings<N extends keyof HawkiUserSettingsSchemas>(
    namespace?: N
): z.infer<HawkiUserSettingsSchemas[N]> {
    const app = useApp();
    return app.userSettings.get(namespace as N);
}
