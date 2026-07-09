<?php
declare(strict_types=1);

namespace App\Services\Ai\ModelInformation\Events;

use App\Models\Ai\AiModel;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

/**
 * Dispatched after the full enrichment pipeline has been applied to all models for a provider
 * during {@see \App\Services\Ai\ModelInformation\ModelInfoFetcher::fetchAll()}.
 *
 * This is the last event in the fetchAll lifecycle. The collection contains all models in their
 * final enriched state, ready to be persisted or returned to the caller.
 *
 * Listeners can use this event to:
 * - Persist or export the enriched model list.
 * - Log a summary of the enriched catalogue for a given provider.
 * - Trigger downstream processes that depend on a fresh model catalogue.
 */
readonly class ModelInfoEnrichedEvent
{
    use Dispatchable;

    public function __construct(
        /** The provider whose models were enriched. */
        public AiProviderProxy $provider,
        /**
         * The fully enriched model collection.
         *
         * @var Collection<int, AiModel>
         */
        public Collection      $enrichedModels,
        /** Structured metrics collector for this fetch run. */
        public JobMetrics      $metrics,
    )
    {
    }
}
