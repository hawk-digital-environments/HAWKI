<?php
declare(strict_types=1);

namespace App\Services\Ai\ModelInformation\Events;

use App\Models\Ai\AiModel;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

/**
 * Dispatched after the provider's model list has been retrieved during
 * {@see \App\Services\Ai\ModelInformation\ModelInfoFetcher::fetchSingle()}, before the
 * target model is located within the list and before enrichment begins.
 *
 * A null collection means the provider adapter call failed. In that case fetchSingle()
 * will return null without dispatching {@see SingleModelInfoEnrichedEvent}.
 *
 * Listeners can use this event to inspect the raw catalogue for a specific lookup or to
 * trace where a model ID came from before the match is resolved.
 */
readonly class SingleModelInfoFetchedEvent
{
    use Dispatchable;

    public function __construct(
        /** The provider whose model catalogue was fetched. */
        public AiProviderProxy $provider,
        /**
         * The raw models returned by the provider adapter, or null when the adapter call failed.
         *
         * @var Collection<int, AiModel>|null
         */
        public Collection|null $models,
        /** The model identifier that is being looked up within the fetched list. */
        public string          $modelId,
        /** Structured metrics collector for this fetch run. */
        public JobMetrics      $metrics,
    )
    {
    }
}
