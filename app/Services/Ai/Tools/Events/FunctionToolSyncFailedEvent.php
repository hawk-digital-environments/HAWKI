<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Events;

use App\Services\Ai\Tools\Contracts\ToolInterface;
use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when a single function tool fails to be persisted during
 * {@see \App\Services\Ai\Tools\FunctionToolSyncer::sync()}.
 *
 * The sync loop continues with the remaining tools after this event fires.
 * The failure is also recorded on the metrics object before dispatch.
 *
 * Listeners can use this event to:
 * - Alert on unexpected tool sync failures.
 * - Collect structured error data for a post-sync report.
 * - Attempt a compensating action (e.g. deactivate the tool record).
 */
readonly class FunctionToolSyncFailedEvent
{
    use Dispatchable;

    public function __construct(
        /** Structured metrics collector for this sync run. */
        public JobMetrics    $metrics,
        /** The tool implementation that could not be persisted. */
        public ToolInterface $tool,
        /** The exception that caused the failure. */
        public \Throwable    $exception,
    )
    {
    }
}
