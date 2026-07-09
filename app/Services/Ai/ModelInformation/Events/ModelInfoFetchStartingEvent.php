<?php
declare(strict_types=1);

namespace App\Services\Ai\ModelInformation\Events;

use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when {@see \App\Services\Ai\ModelInformation\ModelInfoFetcher::fetchAll()} begins
 * processing a provider, before any models are fetched or enriched.
 *
 * Listeners receive the provider and the metrics object so they can log the start of a sync
 * run or pre-populate counters before any work takes place.
 */
readonly class ModelInfoFetchStartingEvent
{
    use Dispatchable;

    public function __construct(
        /** The provider whose model catalogue is about to be fetched. */
        public AiProviderProxy $provider,
        /** Structured metrics collector for this fetch run. */
        public JobMetrics      $metrics,
    )
    {
    }
}
