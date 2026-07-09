<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Events;

use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after all function tools have been iterated during
 * {@see \App\Services\Ai\Tools\FunctionToolSyncer::sync()}, before the metrics
 * completion announcement is made.
 *
 * Listeners may inspect the final metrics to produce a post-sync report or trigger
 * downstream processes that depend on the tool catalogue being up to date.
 */
readonly class FunctionToolSyncCompletedEvent
{
    use Dispatchable;

    public function __construct(
        /** Structured metrics collector containing the results of this sync run. */
        public JobMetrics $metrics,
    )
    {
    }
}
