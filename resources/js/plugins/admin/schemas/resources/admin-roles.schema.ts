import z from 'zod';
import { dbBoolean } from './values.js';

/** Role rows from RoleSchema.php and RoleRepository.php. */
export const AdminRoleSchema = z.object({
    id: z.string(),
    name: z.string(),
    slug: z.string(),
    description: z.string().nullable(),
    is_system: dbBoolean(),
    permissions: z.array(z.string()),
    /** Version for `If-Match` on writes; taken from the resource meta by `adminContent()`, not an attribute. */
    _version: z.string().optional()
});
export default AdminRoleSchema;
/** A row of the section: the resource attributes plus the edit version `adminContent()` copies from the meta. */
export type AdminRoleResource = z.infer<typeof AdminRoleSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'admin-roles': AdminRoleResource;
    }
}
