import z from 'zod';
import { jsonObject } from './values.js';

/** System-model rows from SystemModelSchema.php and SystemModelRepository.php. */
export const AdminSystemModelSchema = z.object({
    id: z.string(),
    model_type: z.string(),
    usage_type: z.enum(['main', 'external']),
    model_id: z.string(),
    prompts: jsonObject(z.string()),
    /** Version for `If-Match` on writes; taken from the resource meta by `adminContent()`, not an attribute. */
    _version: z.string().optional()
});
export default AdminSystemModelSchema;
/** A row of the section: the resource attributes plus the edit version `adminContent()` copies from the meta. */
export type AdminSystemModelResource = z.infer<typeof AdminSystemModelSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'admin-system-models': AdminSystemModelResource;
    }
}
