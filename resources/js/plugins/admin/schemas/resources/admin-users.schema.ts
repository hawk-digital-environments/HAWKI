import z from 'zod';

/** User rows from UserSchema.php and UserRepository.php. */
export const AdminUserSchema = z.object({
    id: z.string(),
    name: z.string(),
    username: z.string(),
    email: z.string(),
    employeetype: z.string(),
    admin_disabled: z.boolean(),
    last_login_at: z.string().nullable(),
    roles: z.array(z.number()),
    mapped_roles: z.array(z.number()),
    local_account: z.boolean(),
    /** Only the built-in system user has this marker. */
    is_system: z.boolean().optional(),
    /** Version for `If-Match` on writes; taken from the resource meta by `adminContent()`, not an attribute. */
    _version: z.string().optional()
});
export default AdminUserSchema;
/**
 * Profile fields the identity provider owns for directory accounts. The editor
 * shows them read-only there and leaves them out of the update payload.
 */
export const directoryManagedFields = ['name', 'username', 'email', 'employeetype'];
/** A row of the section: the resource attributes plus the edit version `adminContent()` copies from the meta. */
export type AdminUserResource = z.infer<typeof AdminUserSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'admin-users': AdminUserResource;
    }
}
