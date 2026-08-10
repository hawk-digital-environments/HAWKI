import z from 'zod';

/**
 * Zod schema for the `migrations` JSON:API resource.
 *
 * The server lists pending client-side migrations via this resource; the
 * {@link MigrationExtension} fetches the collection, finds each migration by
 * `id` (the name the client registered it under), runs the matching migrator,
 * and posts back to `actions/apply`. `data` carries the optional server-computed
 * payload the migrator needs (e.g. the encrypted legacy keychain blob for the
 * `after_passkey` keychain migration).
 *
 * Augments {@link HawkiResourceSchemas} via declaration merging below so the
 * `RestApi` typed accessors (`getResourceCollection('migrations')`) return a
 * typed `Migration[]`.
 */
const MigrationsSchema = z.object({
    /** The migration name. Must match a name registered on the client side. */
    id: z.string(),
    /**
     * Optional server-computed payload for the migrator. Shape is
     * migration-specific (e.g. an encrypted blob); `null` signals the migration
     * has no payload — often the case for users already on the new format.
     */
    data: z.record(z.string(), z.unknown()).nullable().optional()
}).strict();

export default MigrationsSchema;

export type Migration = z.infer<typeof MigrationsSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'migrations': Migration;
    }
}
