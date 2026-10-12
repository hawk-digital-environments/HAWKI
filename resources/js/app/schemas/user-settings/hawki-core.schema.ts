import z from 'zod';

/**
 * Schema for the `'hawki-core'` user-settings namespace — the default,
 * always-present per-user settings namespace.
 *
 * This is what `app.userSettings.get('hawki-core')` returns and what
 * `useUserSettings()` (called without arguments) resolves to. The namespace
 * resource is fetched from `GET /api/hawki/v1/user-settings` during the
 * bootstrapper's `preparation` stage and parsed against this schema on first
 * access.
 *
 * Within the namespace, settings are grouped by each settings class's
 * "public key" (here `core` maps to `CoreUserSettings`), mirroring the
 * JSON:API attributes shape exactly:
 *
 * ```json
 * {"id": "hawki-core", "core": {"locale": null, "theme": "auto", "timezone": "UTC"}}
 * ```
 *
 * ### Fields
 *
 * | Key          | Type                    | Meaning                                                                        |
 * |-------------|-------------------------|--------------------------------------------------------------------------------|
 * | `locale`    | `string \| null`        | The user's preferred locale code (e.g. `"de_DE"`). `null` means "follow the app default" (see `HawkiCoreConfigSchema.locale.default`). |
 * | `theme`     | `'auto' \| 'light' \| 'dark'` | UI colour-scheme preference. `auto` (the default) follows the browser; the literals match the server-side `Theme` enum's backed values. |
 * | `timezone`  | `string`                | IANA timezone identifier (e.g. `"Europe/Berlin"`). Defaults to `"UTC"`.       |
 *
 * ### Writing
 *
 * Use `app.userSettings.save('hawki-core', 'core', {theme: 'dark'})` to
 * persist a partial update. The extension validates the merged result against
 * this schema before sending the PATCH.
 */
const HawkiCoreUserSettingsSchema = z.object({
    core: z.object({
        /** The user's preferred locale code; `null` means "follow the app default". */
        locale: z.string().nullable(),
        /** UI colour-scheme preference. `auto` (the default) follows the browser's
         *  `prefers-color-scheme`; `light` / `dark` pin the scheme. Matches the
         *  server-side `Theme` enum's backed string values. */
        theme: z.enum(['auto', 'light', 'dark']),
        /** IANA timezone identifier, e.g. `"Europe/Berlin"`. */
        timezone: z.string()
    })
});

export default HawkiCoreUserSettingsSchema;

/** Inferred type of the `'hawki-core'` user-settings namespace resource. */
export type HawkiCoreUserSettings = z.infer<typeof HawkiCoreUserSettingsSchema>;

// Augment the user-settings schema registry so `app.userSettings.get('hawki-core')` is typed.
declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiUserSettingsSchemas {
        'hawki-core': typeof HawkiCoreUserSettingsSchema;
    }
}
