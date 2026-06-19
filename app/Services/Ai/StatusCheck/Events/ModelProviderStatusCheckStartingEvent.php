<?php
declare(strict_types=1);

namespace App\Services\Ai\StatusCheck\Events;

use App\Models\Ai\AiProvider;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched at the very beginning of each provider's iteration in the model status
 * check loop — before any adapter resolution or HTTP work is performed.
 *
 * Listeners can use this event to log provider-level progress or attach custom
 * instrumentation. If the provider has no associated models, or its adapter does not
 * support HTTP status checks, the run will skip it without dispatching further events
 * for that provider.
 *
 * @see ModelProviderStatusFetchStartingEvent — fired once the fetcher is ready
 * @see ModelProviderStatusUpdatedEvent       — fired after statuses are persisted
 * @see ModelProviderStatusCheckFailedEvent   — fired if an exception is thrown
 */
readonly class ModelProviderStatusCheckStartingEvent
{
    use Dispatchable;

    public function __construct(
        public AiProvider $provider
    )
    {
    }
}
