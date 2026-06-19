<?php
declare(strict_types=1);

namespace App\Services\Ai\StatusCheck\Events;

use App\Models\Ai\AiProvider;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after the provider adapter's checkModelStatus() has returned but before
 * the resolved statuses are written to the database.
 *
 * The {@see AiModelOnlineStatusCollection} now reflects whatever the adapter determined:
 * models that are reachable at the provider have been marked
 * {@see \App\Services\Ai\Values\OnlineStatus::ONLINE}; others remain
 * {@see \App\Services\Ai\Values\OnlineStatus::UNKNOWN} (they will be set to OFFLINE
 * before persistence). Listeners may read or further adjust the collection at this
 * stage — for example to force a model online or offline for business reasons.
 * The {@see AiModelDemandCollection} is also available, but is not expected to have been modified by the adapter.
 *
 * @see ModelProviderStatusFetchStartingEvent — fired just before the adapter ran
 * @see ModelProviderStatusUpdatedEvent       — fired after the DB write completes
 */
readonly class ModelProviderStatusFetchedEvent
{
    use Dispatchable;

    public function __construct(
        public AiModelOnlineStatusCollection $statusCollection,
        public AiModelDemandCollection       $demandCollection,
        public AiProvider                    $provider
    )
    {
    }
}
