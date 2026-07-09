<?php
declare(strict_types=1);

namespace App\Services\Ai\ModelInformation\Enrichment\Events;

use App\Models\Ai\AiModel;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\LiteLlmModelData;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after the {@see \App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlmApiEnricher}
 * has successfully enriched a model using LiteLLM data.
 *
 * The event carries both the enriched model record and the raw LiteLLM data that was
 * applied, making it possible to inspect exactly which metadata was used.
 *
 * Listeners can use this event to:
 * - Audit which LiteLLM fields were applied to a given model.
 * - Extend enrichment further with data from a second source.
 * - Log pricing or capability changes for monitoring purposes.
 */
readonly class LiteLlmEnrichmentCompletedEvent
{
    use Dispatchable;

    public function __construct(
        /** The model after enrichment has been applied. */
        public AiModel         $modelInfo,
        /** The raw LiteLLM record that was used to enrich the model. */
        public LiteLlmModelData $liteLlmData,
        /** The provider the model belongs to. */
        public AiProviderProxy  $provider,
        /** Structured metrics collector for the current enrichment run. */
        public JobMetrics       $jobMetrics,
    )
    {
    }
}
