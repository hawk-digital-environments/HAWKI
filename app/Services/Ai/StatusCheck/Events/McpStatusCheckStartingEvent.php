<?php
declare(strict_types=1);

namespace App\Services\Ai\StatusCheck\Events;

use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched before the MCP server status check loop begins iterating over any server.
 *
 * Listeners may inspect the initial metrics object or perform any setup needed
 * before pinging starts.
 */
readonly class McpStatusCheckStartingEvent
{
    use Dispatchable;

    public function __construct(
        public JobMetrics $metrics
    )
    {
    }
}
