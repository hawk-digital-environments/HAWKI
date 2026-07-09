<?php
declare(strict_types=1);

namespace App\Services\Ai\ModelInformation\Events;

use App\Models\Ai\AiModel;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

/**
 * Dispatched after the raw model list has been retrieved from a provider adapter during
 * {@see \App\Services\Ai\ModelInformation\ModelInfoFetcher::fetchAll()}, before enrichment begins.
 *
 * A null collection means the provider adapter failed to return a list; enrichment will
 * run against an empty collection in that case.
 *
 * Listeners can use this event to inspect or log the raw model data before it is enriched,
 * or to count how many models a provider reported.
 */
readonly class ModelInfoFetchedEvent
{
    use Dispatchable;

    public function __construct(
        /** The provider whose model catalogue was fetched. */
        public AiProviderProxy   $provider,
        /**
         * The raw models returned by the provider adapter, or null when the adapter call failed.
         *
         * @var Collection<int, AiModel>|null
         */
        public Collection|null   $models,
        /** Structured metrics collector for this fetch run. */
        public JobMetrics        $metrics,
    )
    {
    }
}
