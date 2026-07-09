<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Mcp\Events;

use App\Models\Ai\McpServer;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;
use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after all tools for a single MCP server have been synced and stale tools
 * have been cleaned up.
 *
 * The metrics at this point reflect every tool processed for this server, including any
 * per-tool failures that were caught and recorded.
 */
readonly class McpServerToolSyncCompletedEvent
{
    use Dispatchable;

    public function __construct(
        public McpServer      $server,
        public HawkiMcpClient $client,
        public JobMetrics     $metrics
    )
    {
    }
}
