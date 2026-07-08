<?php
declare(strict_types=1);

namespace App\Services\Ai\ModelInformation\Enrichment\Events;

use App\Models\Ai\AiModel;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlmApiEnricher;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when the {@see LiteLlmApiEnricher} finds no LiteLLM data for a model,
 * meaning no enrichment was applied and the model is returned as-is.
 *
 * Both the live API store and the static fallback store were consulted before this event
 * fires; the absence of data is definitive for this enrichment step.
 *
 * Listeners can use this event to:
 * - Log or alert when a model lacks LiteLLM metadata (e.g. a newly released model).
 * - Apply a custom enrichment fallback for models not covered by LiteLLM.
 * - Track which models are missing pricing or capability data.
 */
readonly class LiteLlmEnrichmentSkippedEvent
{
    use Dispatchable;

    public function __construct(
        /** The model for which no LiteLLM data was found. Its properties are unchanged. */
        public AiModel           $modelInfo,
        /** The provider the model belongs to. */
        public AiProviderProxy   $provider,
        /** Structured metrics collector for the current enrichment run. */
        public JobMetrics        $jobMetrics,
        /** The enricher instance that attempted the lookup. */
        public LiteLlmApiEnricher $enricher,
    )
    {
    }
}
