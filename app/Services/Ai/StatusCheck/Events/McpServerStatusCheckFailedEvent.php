<?php
declare(strict_types=1);

namespace App\Services\Ai\StatusCheck\Events;

use App\Models\Ai\McpServer;
use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when an exception is thrown while attempting to check a server's status.
 *
 * The server has already been marked offline by the time this event fires. No client
 * is available because the failure may have occurred before the connection was established.
 */
readonly class McpServerStatusCheckFailedEvent
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
