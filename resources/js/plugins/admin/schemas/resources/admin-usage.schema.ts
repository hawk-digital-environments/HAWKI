import z from 'zod';
import { dbNumber } from './values.js';

/** Usage summary rows from UsageSchema.php and UsageStatistics.php. */
export const AdminUsageSchema = z.object({
    id: z.string(),
    /** The grouped column's value; grouping by user yields integer ids. */
    label: z.coerce.string(),
    requests: dbNumber(),
    prompt_tokens: dbNumber(),
    completion_tokens: dbNumber(),
    /** Version for `If-Match` on writes; taken from the resource meta by `adminContent()`, not an attribute. */
    _version: z.string().optional()
});
export default AdminUsageSchema;
/** A row of the section: the resource attributes plus the edit version `adminContent()` copies from the meta. */
export type AdminUsageResource = z.infer<typeof AdminUsageSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'admin-usage': AdminUsageResource;
    }
}
