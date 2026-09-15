import z from 'zod';

/** Health rows from HealthSchema.php and HealthMonitor.php. */
export const AdminHealthSchema = z.object({
    id: z.string(),
    name: z.string(),
    status: z.string(),
    message: z.string().nullable(),
    /** Only checks that measure a response time set it; unmeasured ones send null. */
    response_time: z.number().nullable().optional(),
    /** Version for `If-Match` on writes; taken from the resource meta by `adminContent()`, not an attribute. */
    _version: z.string().optional()
});
export default AdminHealthSchema;
/** A row of the section: the resource attributes plus the edit version `adminContent()` copies from the meta. */
export type AdminHealthResource = z.infer<typeof AdminHealthSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'admin-health': AdminHealthResource;
    }
}
