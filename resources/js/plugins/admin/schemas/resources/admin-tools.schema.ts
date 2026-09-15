import z from 'zod';
import { AccessRuleNameSchema } from '../admin-content.js';

/** Tool rows from ToolSchema.php and ToolRepository.php. */
export const AdminToolSchema = z.object({
    id: z.string(),
    name: z.string(),
    kind: z.string(),
    active: z.boolean(),
    access_rule: AccessRuleNameSchema.default('unavailable'),
    mapped_capability: z.string().nullable(),
    description: z.string().nullable(),
    models: z.array(z.number()),
    /** Version for `If-Match` on writes; taken from the resource meta by `adminContent()`, not an attribute. */
    _version: z.string().optional()
});
export default AdminToolSchema;
/** A row of the section: the resource attributes plus the edit version `adminContent()` copies from the meta. */
export type AdminToolResource = z.infer<typeof AdminToolSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'admin-tools': AdminToolResource;
    }
}
