import z from 'zod';
import { jsonObject } from './values.js';

/** Model rows from ModelSchema.php and ModelRepository.php. */
export const AdminModelSchema = z.object({
    id: z.string(),
    label: z.string(),
    model_id: z.string(),
    provider_id: z.number(),
    active: z.boolean(),
    status: z.string(),
    descriptions: jsonObject(z.string()),
    model_type: z.string().nullable(),
    documentation_url: z.string().nullable(),
    deprecation_date: z.string().nullable(),
    input: z.array(z.string()).nullable(),
    output: z.array(z.string()).nullable(),
    parameters: jsonObject(z.unknown()).nullable(),
    native_capabilities: z.array(z.string()).nullable(),
    settings: jsonObject(z.unknown()).nullable(),
    limits: jsonObject(z.unknown()).nullable(),
    pricing: jsonObject(z.unknown()).nullable(),
    flags: z.array(z.string()).nullable(),
    tools: z.array(z.number()),
    allowed_roles: z.array(z.number()),
    usage_rules: z.array(z.enum(['main', 'external'])),
    /** Version for `If-Match` on writes; taken from the resource meta by `adminContent()`, not an attribute. */
    _version: z.string().optional()
});
export default AdminModelSchema;
/** A row of the section: the resource attributes plus the edit version `adminContent()` copies from the meta. */
export type AdminModelResource = z.infer<typeof AdminModelSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'admin-models': AdminModelResource;
    }
}
