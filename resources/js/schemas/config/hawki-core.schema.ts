import z from 'zod';
import {LocalesSchema} from '$lib/schemas/resources/compound/locales.schema.js';

const HawkiCoreSchema = z.object({
    locale: z.object({
        default: z.string(),
        available: z.array(LocalesSchema)
    }),
    transfer: z.object({
        baseUrl: z.string(),
        websocket: z.object({
            key: z.string().nullable(),
            host: z.string(),
            port: z.number(),
            forceTls: z.boolean(),
            path: z.string()
        }).optional()
    }),
    storage_avatars: z.object({
        maxFileSize: z.number(),
        allowedMimeTypes: z.array(z.string()),
        allowedExtensions: z.array(z.string())
    }).optional(),
    storage_files: z.object({
        maxFileSize: z.number(),
        allowedMimeTypes: z.array(z.string()),
        allowedExtensions: z.array(z.string())
    }).optional(),
    ai: z.object({
        handle: z.string(),
        hawkiUserDisplayName: z.string(),
        hawkiUserUsername: z.string(),
        hawkiUserAvatar: z.string()
    }).optional(),
    salts: z.object({
        userdata: z.string(),
        invitation: z.string().optional(),
        ai: z.string().optional(),
        passkey: z.string(),
        backup: z.string()
    }).optional(),
    security: z.object({
        passkeyAllowPaste: z.boolean(),
        passkeyRestrictCharacters: z.boolean()
    })
});

export default HawkiCoreSchema;

// Augment the schema registry to include our config schema, so that getConfig() can infer the correct type.
declare module '$lib/data/config/config.js' {
    interface ConfigSchemaRegistry {
        'hawki-core': typeof HawkiCoreSchema;
    }
}
