<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Mcp\Events;

use App\Models\Ai\McpServer;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;
use App\Services\Ai\Tools\Values\McpToolDefinition;
use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when a single tool definition from an MCP server could not be persisted.
 *
 * The server and client are provided so listeners can correlate the failure with the
 * originating connection. The definition contains the raw data that was being processed
 * when the error occurred.
 */
readonly class McpToolSyncFailedEvent
{
    use Dispatchable;

    public function __construct(
        public McpServer         $server,
        public HawkiMcpClient    $client,
        public McpToolDefinition $definition,
        public \Throwable        $exception,
        public JobMetrics        $metrics
    )
    {
    }
}
