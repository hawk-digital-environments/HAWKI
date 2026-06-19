<?php
declare(strict_types=1);

namespace App\Services\Ai\StatusCheck\Events;

use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after the AI model status check loop has finished processing all providers.
 *
 * The metrics contain aggregate counters and any errors collected during the entire run:
 * - `model_count`   — total number of models for which a status change was recorded
 * - `model_online`  — models whose status was determined to be online
 * - `model_offline` — models whose status was determined to be offline
 *
 * Listeners may use this event to send summary notifications, update dashboards, or
 * trigger follow-up jobs. Check {@see JobMetrics::hasErrors()} to distinguish a clean
 * run from one where individual providers encountered errors.
 *
 * @see ModelStatusCheckStartingEvent — the corresponding start-of-run event
 */
readonly class ModelStatusCheckCompletedEvent
{
    use Dispatchable;

    public function __construct(
        public JobMetrics $metrics
    )
    {
    }
}
