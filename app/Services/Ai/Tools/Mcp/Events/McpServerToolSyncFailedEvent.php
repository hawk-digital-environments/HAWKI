<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Mcp\Events;

use App\Models\Ai\McpServer;
use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when the syncer fails to process an entire MCP server — for example because
 * the connection could not be established or the tool list could not be retrieved.
 *
 * Unlike {@see McpToolSyncFailedEvent}, which is fired per-tool, this event means the
 * whole server was skipped. No client is provided because the failure typically occurs
 * before or during client creation.
 */
readonly class McpServerToolSyncFailedEvent
{
    use Dispatchable;

    public function __construct(
        public McpServer  $server,
        public \Throwable $exception,
        public JobMetrics $metrics
    )
    {
    }
}
