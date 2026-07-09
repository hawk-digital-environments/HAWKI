<?php
declare(strict_types=1);

namespace App\Services\Ai\ModelInformation\Events;

use App\Models\Ai\AiModel;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after a single model has been fully enriched during
 * {@see \App\Services\Ai\ModelInformation\ModelInfoFetcher::fetchSingle()}.
 *
 * This is the last event in the fetchSingle lifecycle, fired only when the model
 * was found in the provider's catalogue and the enrichment pipeline produced a result.
 * When the model is not found or enrichment returns empty, this event is not dispatched.
 *
 * Listeners can use this event to:
 * - Persist or cache the enriched model record.
 * - Trigger downstream processes that depend on fresh single-model data.
 * - Log a trace of completed single-model enrichment for auditing.
 */
readonly class SingleModelInfoEnrichedEvent
{
    use Dispatchable;

    public function __construct(
        /** The provider the model belongs to. */
        public AiProviderProxy $provider,
        /** The fully enriched model record ready to be returned to the caller. */
        public AiModel         $enrichedModelInfo,
        /** The model identifier that was looked up (e.g. "gpt-4o"). */
        public string          $modelId,
        /** Structured metrics collector for this fetch run. */
        public JobMetrics      $metrics,
    )
    {
    }
}
