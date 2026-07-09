<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Events;

use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when {@see \App\Services\Ai\Tools\FunctionToolSyncer::sync()} begins,
 * before any function tool is iterated or persisted.
 *
 * Listeners may inspect or pre-populate the metrics object before any work starts.
 */
readonly class FunctionToolSyncStartingEvent
{
    use Dispatchable;

    public function __construct(
        /** Structured metrics collector for this sync run. */
        public JobMetrics $metrics,
    )
    {
    }
}
