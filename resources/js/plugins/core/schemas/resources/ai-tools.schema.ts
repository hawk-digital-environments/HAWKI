import z from 'zod';
import McpServersSchema from '$plugins/core/schemas/resources/mcp-servers.schema.js';

/**
 * Validates the `ai-tools` API resource — the tools a user can attach to a chat message
 * (e.g. web search, an MCP-backed tool), rendered in the composer's tool menu/chips
 * (see `ToolMenu.svelte`, `ToolChips.svelte`, `ToolSlice.svelte.ts`).
 *
 * Registers the resource under the key `'ai-tools'` in `HawkiResourceSchemas` (see the
 * `declare module` augmentation below).
 */
const AiToolsSchema = z.object({
    id: z.string(),
    name: z.string(),
    description: z.string(),
    /** Links this tool to an entry in the `ai-tool-capabilities` resource (its `id`), used to look up the capability's label/icon; `null` if the tool doesn't map to a known capability. */
    capability_key: z.string().nullable(),
    status: z.enum(['online', 'offline', 'unknown']),
    created_at: z.string(),
    updated_at: z.string(),
    /** The MCP server backing this tool, if it is implemented as an MCP tool rather than a built-in one. */
    server: z.optional(McpServersSchema).nullable()
});

export default AiToolsSchema;

export type AiTool = z.infer<typeof AiToolsSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'ai-tools': AiTool;
    }
}
