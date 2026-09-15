import z from 'zod';

/** Role-mapping rows from RoleMappingSchema.php and RoleMappingRepository.php. */
export const AdminRoleMappingSchema = z.object({
    id: z.string(),
    employee_type: z.string(),
    role_id: z.number(),
    /** Version for `If-Match` on writes; taken from the resource meta by `adminContent()`, not an attribute. */
    _version: z.string().optional()
});
export default AdminRoleMappingSchema;
/** A row of the section: the resource attributes plus the edit version `adminContent()` copies from the meta. */
export type AdminRoleMappingResource = z.infer<typeof AdminRoleMappingSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'admin-mappings': AdminRoleMappingResource;
    }
}
