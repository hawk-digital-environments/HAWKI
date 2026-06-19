<?php
declare(strict_types=1);

namespace App\Services\Ai\StatusCheck\Events;

use App\Models\Ai\McpServer;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;
use App\Services\Ai\Values\OnlineStatus;
use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after a ping was performed and the server's online status has been
 * persisted — regardless of whether the outcome is online or offline.
 *
 * The resolved $status reflects what was written to the database. Listeners that need
 * to react differently to online vs. offline results should branch on $status->value.
 */
readonly class McpServerStatusCheckedEvent
{
    use Dispatchable;

    public function __construct(
        public McpServer      $server,
        public HawkiMcpClient $client,
        public OnlineStatus   $status,
        public JobMetrics     $metrics
    )
    {
    }
}
