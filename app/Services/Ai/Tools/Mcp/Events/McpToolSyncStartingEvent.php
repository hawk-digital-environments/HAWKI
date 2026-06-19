<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Mcp\Events;

use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched before the MCP tool sync loop begins iterating over any server.
 *
 * Listeners may inspect or pre-populate the metrics object before any work starts.
 */
readonly class McpToolSyncStartingEvent
{
    use Dispatchable;

    public function __construct(
        public JobMetrics $metrics
    )
    {
    }
}
