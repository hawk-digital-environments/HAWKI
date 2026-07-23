<?php
declare(strict_types=1);

namespace App\Services\Ai\StatusCheck\Events;

use App\Models\Ai\AiProvider;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched immediately before the provider adapter's checkModelStatus() is called.
 *
 * At this point the {@see AiModelOnlineStatusCollection} has been initialised with all
 * models belonging to the provider (all set to {@see \App\Services\Ai\Values\OnlineStatus::UNKNOWN}).
 * The {@see AiModelDemandCollection} has also been initialised, but all demand levels are still at their default (LOW).
 * No HTTP requests have been made yet.
 *
 * Listeners may inspect the models that are about to be checked, or substitute /
 * pre-populate statuses in the collection before the adapter runs.
 *
 * @see ModelProviderStatusFetchedEvent — fired once the adapter has finished
 */
readonly class ModelProviderStatusFetchStartingEvent
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
