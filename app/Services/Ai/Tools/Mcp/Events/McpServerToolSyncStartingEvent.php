<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Mcp\Events;

use App\Models\Ai\McpServer;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;
use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched just before the syncer begins processing the tool list for a single MCP server.
 *
 * Listeners receive both the server model and the already-connected client so they can
 * perform additional queries or inject extra behaviour into the sync loop for this server.
 */
readonly class McpServerToolSyncStartingEvent
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
