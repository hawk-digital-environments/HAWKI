import z from 'zod';
import {LocalesSchema} from '$lib/app/schemas/resources/compound/locales.schema.js';

/**
 * Schema for the `'hawki-core'` config namespace — the app's default,
 * always-present configuration namespace.
 *
 * This is the config every `useConfig()` call (with no arguments) resolves
 * to; it is also what `app.config.get('hawki-core')` returns. The namespace
 * is populated from the server's `'configs'` resource (fetched during the
 * bootstrapper's `preparation` stage and after connection-type changes) and
 * parsed against this schema on first access.
 *
 * Fields marked `.optional()` are features/settings that may be disabled or
 * not configured server-side (e.g. no avatar storage limits configured);
 * always guard with an `if` or `?.` before reading into them.
 */
const HawkiCoreSchema = z.object({
    locale: z.object({
        /** The default locale code the frontend should use when no user preference is available, e.g. `"en_US"`. */
        default: z.string(),
        /** All locales the backend has enabled/translated, used to populate locale switchers. */
        available: z.array(LocalesSchema)
    }),
    transfer: z.object({
        /** Base URL used to build absolute links to the backend API. */
        baseUrl: z.string(),
        /** Present only if realtime features (e.g. live chat updates) are enabled server-side. */
        websocket: z.object({
            key: z.string().nullable(),
            host: z.string(),
            port: z.number(),
            forceTls: z.boolean(),
            path: z.string()
        }).optional()
    }),
    /** Upload constraints for avatar images; absent if avatar upload is disabled. */
    storage_avatars: z.object({
        maxFileSize: z.number(),
        allowedMimeTypes: z.array(z.string()),
        allowedExtensions: z.array(z.string())
    }).optional(),
    /** Upload constraints for chat file attachments; absent if file uploads are disabled. */
    storage_files: z.object({
        maxFileSize: z.number(),
        allowedMimeTypes: z.array(z.string()),
        allowedExtensions: z.array(z.string())
    }).optional(),
    /** Display info for the built-in "HAWKI AI" pseudo-user shown in chats. */
    ai: z.object({
        handle: z.string(),
        hawkiUserDisplayName: z.string(),
        hawkiUserUsername: z.string(),
        hawkiUserAvatar: z.string()
    }).optional(),
    /**
     * Per-purpose salts for the frontend's client-side key derivation
     * (`deriveKey()` in `kernel/keychain/keychainHandle.ts` and the legacy
     * `public/js/encryption.js`/`handshake_functions.js`), so a key derived for
     * one purpose can't be reused for another: `userdata` derives the keychain
     * encryptor key from the user's passkey, `ai` derives a room's AI
     * conversation key, `invitation` derives keys for room-invitation flows,
     * `passkey` derives the passkey-based encryption key itself, and `backup`
     * derives the passkey backup/recovery key. `ai`/`invitation` are optional —
     * absent when the corresponding feature isn't configured server-side.
     */
    salts: z.object({
        userdata: z.string(),
        invitation: z.string().optional(),
        ai: z.string().optional(),
        passkey: z.string(),
        backup: z.string()
    }).optional(),
    security: z.object({
        /** Whether users are allowed to paste text into passkey/PIN input fields (paste is sometimes blocked to force manual entry/reduce clipboard leakage). */
        passkeyAllowPaste: z.boolean(),
        /** Whether passkey/PIN input should restrict which characters are accepted while typing. */
        passkeyRestrictCharacters: z.boolean()
    })
});

export default HawkiCoreSchema;

// Augment the schema registry to include our config schema, so that getConfig() can infer the correct type.
declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiConfigSchemas {
        'hawki-core': typeof HawkiCoreSchema;
    }
}
