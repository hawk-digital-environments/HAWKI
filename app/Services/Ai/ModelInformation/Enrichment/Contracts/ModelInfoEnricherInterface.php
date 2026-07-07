<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation\Enrichment\Contracts;


use App\Models\Ai\AiModel;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Utils\JobMetrics;

/**
 * Contract for a single step in the model-info enrichment pipeline.
 *
 * An enricher receives a partially populated {@see AiModel}, augments it with
 * information from its own data source, and returns the updated instance.
 * Enrichers must be non-destructive: they should only fill in fields that are
 * not yet set, so that earlier enrichers' data is never overwritten.
 *
 * Implementations are registered on the {@see AiModelInfoEnrichmentPipeline}.
 */
interface ModelInfoEnricherInterface
{
    /**
     * Enriches the model with data from this enricher's source.
     *
     * The returned model may be the same (mutated) instance or a replacement.
     * On failure, return $modelInfo unchanged rather than throwing — the caller
     * logs errors at the pipeline level.
     */
    public function enrichModelInfo(
        AiModel         $modelInfo,
        AiProviderProxy $provider,
        JobMetrics      $jobMetrics
    ): AiModel;
}
