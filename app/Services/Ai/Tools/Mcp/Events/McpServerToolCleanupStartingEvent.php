<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Mcp\Events;

use App\Models\Ai\McpServer;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;
use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched before the syncer removes stale tools that were not reported by the server
 * in the current sync run.
 *
 * Listeners may inspect or extend $syncedToolIds to influence which tools survive the
 * cleanup pass (e.g. to protect manually pinned tools from deletion).
 *
 * @property-read array<int, int> $syncedToolIds IDs of AiTool records that were successfully upserted
 *                                               during this sync pass and must not be removed.
 */
readonly class McpServerToolCleanupStartingEvent
{
    use Dispatchable;

    public function __construct(
        public McpServer      $server,
        public HawkiMcpClient $client,
        public array          $syncedToolIds,
        public JobMetrics     $metrics
    )
    {
    }
}
