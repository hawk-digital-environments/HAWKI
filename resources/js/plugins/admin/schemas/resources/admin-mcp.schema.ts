import z from 'zod';
import { jsonObject } from './values.js';

/** MCP server rows from McpServerSchema.php and McpServerRepository.php. */
export const AdminMcpServerSchema = z.object({
    id: z.string(),
    server_label: z.string(),
    kind: z.enum(['http', 'sse', 'stdio']),
    url: z.string(),
    status: z.enum(['online', 'offline', 'unknown']),
    api_key_set: z.boolean(),
    additional_config_set: z.boolean(),
    description: z.string().nullable(),
    require_approval: z.enum(['never', 'always']),
    /** Per-connection timeout values in seconds. */
    timeouts: jsonObject(z.unknown()).nullable(),
    /** Version for `If-Match` on writes; taken from the resource meta by `adminContent()`, not an attribute. */
    _version: z.string().optional()
});
export default AdminMcpServerSchema;
/** A row of the section: the resource attributes plus the edit version `adminContent()` copies from the meta. */
export type AdminMcpServerResource = z.infer<typeof AdminMcpServerSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'admin-mcp': AdminMcpServerResource;
    }
}
