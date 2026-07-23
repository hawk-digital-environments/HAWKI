<?php
declare(strict_types=1);

namespace App\Services\Ai\StatusCheck\Events;

use App\Models\Ai\AiProvider;
use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when an unhandled exception is thrown while processing a provider during
 * the model status check loop.
 *
 * The exception has already been caught, logged, and recorded as an error on $metrics
 * by the time this event fires. The remaining providers in the loop will still be
 * processed. Listeners may use this event to trigger alerts or custom error-handling
 * logic per provider.
 *
 * Note: this event fires for any failure in the provider's iteration — including
 * adapter resolution, HTTP fetching, and database persistence errors.
 *
 * @see ModelProviderStatusCheckStartingEvent — the corresponding start event
 */
readonly class ModelProviderStatusCheckFailedEvent
{
    use Dispatchable;

    public function __construct(
        public AiProvider $provider,
        public \Throwable $exception,
        public JobMetrics $metrics
    )
    {
    }
}
