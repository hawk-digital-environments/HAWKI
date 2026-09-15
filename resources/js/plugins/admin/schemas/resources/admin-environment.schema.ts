import z from 'zod';

/** Environment rows from EnvironmentSchema.php and SystemSettings.php. */
export const AdminEnvironmentSchema = z.object({
    id: z.string(),
    key: z.string(),
    /** Includes redacted secret markers such as `[set]` and `[not set]`. */
    value: z.unknown(),
    source: z.enum(['database', 'environment', 'deployment']),
    /** Version for `If-Match` on writes; taken from the resource meta by `adminContent()`, not an attribute. */
    _version: z.string().optional()
});
export default AdminEnvironmentSchema;
/** A row of the section: the resource attributes plus the edit version `adminContent()` copies from the meta. */
export type AdminEnvironmentResource = z.infer<typeof AdminEnvironmentSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'admin-environment': AdminEnvironmentResource;
    }
}
