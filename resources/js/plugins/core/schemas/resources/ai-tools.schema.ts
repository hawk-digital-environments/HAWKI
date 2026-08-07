import z from 'zod';
import McpServersSchema from '$plugins/core/schemas/resources/mcp-servers.schema.js';

const AiToolsSchema = z.object({
    id: z.string(),
    name: z.string(),
    description: z.string(),
    capability_key: z.string().nullable(),
    status: z.enum(['online', 'offline', 'unknown']),
    created_at: z.string(),
    updated_at: z.string(),
    server: z.optional(McpServersSchema).nullable()
});

export default AiToolsSchema;

export type AiTool = z.infer<typeof AiToolsSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'ai-tools': AiTool;
    }
}
