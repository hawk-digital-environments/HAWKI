import z from 'zod';

/**
 * Validates the `mcp-servers` API resource — a configured MCP (Model Context Protocol) server
 * that backs one or more `ai-tools` entries (see `AiToolsSchema.server`). Regular users only see
 * the public fields; the "Admin-only fields" below are additionally returned when the requester
 * has admin permissions (mirrors `App\JsonApi\V1\McpServers\McpServerSchema`'s field visibility).
 *
 * Registers the resource under the key `'mcp-servers'` in `HawkiResourceSchemas` (see the
 * `declare module` augmentation below).
 */
const McpServersSchema = z.object({
    id: z.string(),
    server_label: z.string(),
    status: z.string(),
    description: z.string().nullable(),
    /** Whether tool calls to this server require explicit user approval before running, e.g. `'never'` or `'always'` (see `AddMcpServer` console command for accepted values). */
    require_approval: z.string(),
    created_at: z.string(),
    updated_at: z.string(),
    // Admin-only fields
    /** Transport/connection type of the MCP server (e.g. HTTP/SSE), admin-only. */
    type: z.string().optional(),
    /** The server's endpoint URL, admin-only. */
    url: z.string().optional(),
    version: z.string().nullable().optional(),
    /** MCP protocol version the server speaks, admin-only. */
    protocol_version: z.string().nullable().optional(),
    /** Per-operation timeout overrides for this server, admin-only. */
    timeouts: z.record(z.string(), z.unknown()).nullable().optional(),
    /** Secret used to authenticate against the server; admin-only and never populated for non-admins. */
    api_key: z.string().nullable().optional(),
    /**
     * Whether this server was provisioned from a static config file rather than the admin UI/DB
     * directly; file-managed servers are synced/removed automatically by
     * `App\Services\Ai\Tools\Repositories\McpServerRepository`. Admin-only, temporary until config
     * files are phased out.
     */
    added_by_file: z.boolean().optional(),
    /** Provider/transport-specific extra settings not covered by the other fields, admin-only. */
    additional_config: z.record(z.string(), z.unknown()).nullable().optional()
});

export default McpServersSchema;

export type McpServer = z.infer<typeof McpServersSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'mcp-servers': McpServer;
    }
}
