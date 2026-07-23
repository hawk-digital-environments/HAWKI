<?php
declare(strict_types=1);

namespace App\Services\Ai\StatusCheck\Events;

use App\Models\Ai\AiProvider;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after all model statuses for a provider have been successfully persisted
 * to the database.
 *
 * This is the "happy-path" completion event for a single provider iteration. All
 * status changes determined by the adapter have been written at this point. Listeners
 * may use this event to trigger cache invalidation, send notifications, or record
 * audit entries on a per-provider basis.
 *
 * @see ModelProviderStatusCheckFailedEvent — fired instead when persistence throws
 * @see ModelStatusCheckCompletedEvent      — fired once all providers are done
 */
readonly class ModelProviderStatusUpdatedEvent
{
    use Dispatchable;

    public function __construct(
        public AiProvider $provider
    )
    {
    }
}
