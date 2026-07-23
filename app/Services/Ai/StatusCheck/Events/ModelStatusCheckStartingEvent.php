<?php
declare(strict_types=1);

namespace App\Services\Ai\StatusCheck\Events;

use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched before the AI model status check loop begins iterating over any provider.
 *
 * Listeners may inspect the initial (empty) metrics object or perform any setup needed
 * before provider pinging starts. At the time this event fires the list of active
 * providers has already been loaded successfully from the database.
 *
 * @see ModelStatusCheckCompletedEvent — the corresponding end-of-run event
 */
readonly class ModelStatusCheckStartingEvent
{
    use Dispatchable;

    public function __construct(
        public JobMetrics $metrics
    )
    {
    }
}
