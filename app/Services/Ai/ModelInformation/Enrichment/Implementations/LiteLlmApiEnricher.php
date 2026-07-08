<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation\Enrichment\Implementations;


use App\Models\Ai\AiModel;
use App\Services\Ai\ModelInformation\Enrichment\Contracts\ModelInfoEnricherInterface;
use App\Services\Ai\ModelInformation\Enrichment\Events\LiteLlmEnrichmentCompletedEvent;
use App\Services\Ai\ModelInformation\Enrichment\Events\LiteLlmEnrichmentSkippedEvent;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\Applier\ChatModelInfoApplier;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\LiteLlmApiDataStore;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\LiteLlmModelData;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\StaticLiteLlmDataStore;
use App\Services\Ai\Models\ModelTypes\Values\WellKnownModelTypes;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Utils\JobMetrics;

/**
 * Enriches a model using metadata from the LiteLLM model catalog.
 *
 * First attempts to resolve metadata from the live {@see LiteLlmApiDataStore} (which
 * caches API responses for 24 hours). On API failure it falls back to the
 * {@see StaticLiteLlmDataStore} (pre-generated PHP files committed to the repository).
 *
 * Once data is found, the model type is inferred from the LiteLLM `mode` field and
 * enrichment is delegated to a type-specific applier — currently only chat models are
 * handled via {@see ChatModelInfoApplier}.
 */
readonly class LiteLlmApiEnricher implements ModelInfoEnricherInterface
{
    public function __construct(
        private LiteLlmApiDataStore    $apiDataStore,
        private StaticLiteLlmDataStore $staticDataStore
    )
    {
    }

    public function enrichModelInfo(AiModel $modelInfo, AiProviderProxy $provider, JobMetrics $jobMetrics): AiModel
    {
        if (empty($modelInfo->model_id)) {
            return $modelInfo;
        }

        try {
            $liteLlmData = $this->apiDataStore->getModelInformation($provider, $modelInfo->model_id);
        } catch (\Throwable $e) {
            // If the API call fails, fall back to static data store
            $liteLlmData = $this->staticDataStore->getModelInformation($provider, $modelInfo->model_id);
        }

        if (!$liteLlmData) {
            LiteLlmEnrichmentSkippedEvent::dispatch($modelInfo, $provider, $jobMetrics, $this);
            return $modelInfo;
        }

        if ($modelInfo->model_type === null) {
            $modelInfo->model_type = $this->resolveModelTypeByData($liteLlmData);
        }

        if ($modelInfo->model_type === WellKnownModelTypes::CHAT) {
            $modelInfo = (new ChatModelInfoApplier())->apply($modelInfo, $liteLlmData);
        }

        LiteLlmEnrichmentCompletedEvent::dispatch($modelInfo, $liteLlmData, $provider, $jobMetrics);

        return $modelInfo;
    }

    /**
     * Maps the LiteLLM `mode` field to a {@see WellKnownModelTypes} constant.
     *
     * Returns null for unrecognised or missing mode values.
     */
    private function resolveModelTypeByData(LiteLlmModelData $data): string|null
    {
        $mode = $data->mode;
        if (in_array($mode, ['chat', 'completions', 'response'], true)) {
            return WellKnownModelTypes::CHAT;
        }

        if ($mode === 'image_generation') {
            return WellKnownModelTypes::IMAGE_GENERATION;
        }

        if ($mode === 'video_generation') {
            return WellKnownModelTypes::VIDEO_GENERATION;
        }

        return null;
    }
}
