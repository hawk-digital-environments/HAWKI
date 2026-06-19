import z from 'zod';

const McpServersSchema = z.object({
    id: z.string(),
    server_label: z.string(),
    status: z.string(),
    description: z.string().nullable(),
    require_approval: z.string(),
    created_at: z.string(),
    updated_at: z.string(),
    // Admin-only fields
    type: z.string().optional(),
    url: z.string().optional(),
    version: z.string().nullable().optional(),
    protocol_version: z.string().nullable().optional(),
    timeouts: z.record(z.string(), z.unknown()).nullable().optional(),
    api_key: z.string().nullable().optional(),
    added_by_file: z.boolean().optional(),
    additional_config: z.record(z.string(), z.unknown()).nullable().optional()
});

export default McpServersSchema;

export type McpServer = z.infer<typeof McpServersSchema>;

declare module '$lib/data/resources/resourceRegistry.js' {
    interface ResourceSchemaRegistry {
        'mcp-servers': McpServer;
    }
}
