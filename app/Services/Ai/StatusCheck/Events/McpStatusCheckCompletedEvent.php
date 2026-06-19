<?php
declare(strict_types=1);

namespace App\Services\Ai\StatusCheck\Events;

use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after the MCP server status check loop has finished processing all servers.
 *
 * The metrics contain aggregate counts (e.g. 'online', 'offline') and any errors
 * collected across the entire run. Listeners may use this to send summary notifications
 * or update dashboards.
 */
readonly class McpStatusCheckCompletedEvent
{
    use Dispatchable;

    public function __construct(
        public JobMetrics $metrics
    )
    {
    }
}
