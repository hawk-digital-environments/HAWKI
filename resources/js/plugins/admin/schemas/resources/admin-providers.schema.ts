import z from 'zod';
import { jsonObject } from './values.js';

/** Provider rows from ProviderSchema.php and ProviderRepository.php. */
export const AdminProviderSchema = z.object({
    id: z.string(),
    name: z.string(),
    provider_id: z.string(),
    adapter_key: z.string(),
    active: z.boolean(),
    api_key_set: z.boolean(),
    api_url: z.string().nullable(),
    model_status_url: z.string().nullable(),
    additional_config_set: z.boolean(),
    /** Adapter-specific provider configuration. */
    settings: jsonObject(z.unknown()).nullable(),
    /** SVG/source metadata or legacy storage UUIDs. */
    icon: jsonObject(z.unknown()).nullable(),
    icon_url: z.string().nullable(),
    icon_url_dark: z.string().nullable(),
    /** Version for `If-Match` on writes; taken from the resource meta by `adminContent()`, not an attribute. */
    _version: z.string().optional()
});
export default AdminProviderSchema;
/** A row of the section: the resource attributes plus the edit version `adminContent()` copies from the meta. */
export type AdminProviderResource = z.infer<typeof AdminProviderSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'admin-providers': AdminProviderResource;
    }
}
