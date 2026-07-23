<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Mcp\Events;

use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after the MCP tool sync loop has finished processing all servers.
 *
 * The metrics contain the aggregate totals and any errors collected across every server
 * that was processed in this run.
 */
readonly class McpToolSyncCompletedEvent
{
    use Dispatchable;

    public function __construct(
        public JobMetrics $metrics
    )
    {
    }
}
