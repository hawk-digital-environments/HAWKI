import z from 'zod';

/** Setting rows from SettingSchema.php and SystemSettings.php. */
export const AdminSettingSchema = z.object({
    id: z.string(),
    key: z.string(),
    value: z.unknown(),
    default: z.unknown(),
    /** The backend's `type` field is exposed as `kind` to avoid JSON:API's reserved key. */
    kind: z.string(),
    options: z.array(z.object({ value: z.string(), label: z.string() })),
    source: z.enum(['database', 'environment']),
    /** Version for `If-Match` on writes; taken from the resource meta by `adminContent()`, not an attribute. */
    _version: z.string()
});
export default AdminSettingSchema;
/** A row of the section: the resource attributes plus the edit version `adminContent()` copies from the meta. */
export type AdminSettingResource = z.infer<typeof AdminSettingSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'admin-settings': AdminSettingResource;
    }
}
