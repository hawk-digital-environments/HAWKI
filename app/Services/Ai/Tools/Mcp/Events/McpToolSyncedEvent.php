<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Mcp\Events;

use App\Models\Ai\AiTool;
use App\Models\Ai\McpServer;
use App\Services\Ai\Tools\Values\McpToolDefinition;
use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after a single MCP tool definition has been successfully upserted into the
 * database during {@see \App\Services\Ai\Tools\Mcp\McpToolSyncer::sync()}.
 *
 * Listeners can use this event to:
 * - Log or audit which tool definitions were synced from a specific MCP server.
 * - Trigger downstream actions that depend on a particular tool being registered.
 * - Associate the persisted tool record with external configuration or metadata.
 */
readonly class McpToolSyncedEvent
{
    use Dispatchable;

    public function __construct(
        /** The MCP server the tool definition was retrieved from. */
        public McpServer         $server,
        /** The raw tool definition returned by the MCP server. */
        public McpToolDefinition $definition,
        /** The persisted database record that was created or updated. */
        public AiTool            $synced,
        /** Structured metrics collector for this sync run. */
        public JobMetrics        $metrics,
    )
    {
    }
}
