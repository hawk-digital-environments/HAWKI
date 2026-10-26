<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation\Enrichment\Implementations;


use App\Models\Ai\AiModel;
use App\Services\Ai\ModelInformation\Enrichment\Contracts\ModelInfoEnricherInterface;
use App\Services\Ai\ModelInformation\Enrichment\ModelInfoEnrichingTrait;
use App\Services\Ai\Providers\Adapters\Implementations\GeminiAdapter;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Utils\JobMetrics;

/**
 * Enricher that applies handcrafted flags to models served by the Google Gemini API.
 *
 * The Gemini `/models` endpoint does not expose every API quirk of a model. Known quirks are
 * maintained manually in the static DATA array, keyed by model ID, and read back by
 * {@see GeminiAdapter} when building the request.
 *
 * Only models of a provider using the {@see GeminiAdapter} are affected, so the same model ID
 * served by another provider (e.g. Gemma on GWDG) is left untouched.
 */
class StaticGoogleEnricher implements ModelInfoEnricherInterface
{
    use ModelInfoEnrichingTrait;

    private const array DATA = [
        'gemma-4-26b-a4b-it' => [
            'flags' => [
                GeminiAdapter::FLAG_NO_THINKING_BUDGET,
            ],
        ],
        'gemma-4-31b-it' => [
            'flags' => [
                GeminiAdapter::FLAG_NO_THINKING_BUDGET,
            ],
        ],
    ];

    /**
     * Looks up the model's ID in the static DATA table and attaches its flags.
     *
     * Returns the model unchanged when the provider is not a Gemini provider or its ID is not in DATA.
     */
    public function enrichModelInfo(AiModel $modelInfo, AiProviderProxy $provider, JobMetrics $jobMetrics): AiModel
    {
        if (!$provider->adapter instanceof GeminiAdapter) {
            return $modelInfo;
        }

        $modelData = self::DATA[$modelInfo->model_id] ?? null;
        if ($modelData === null) {
            return $modelInfo;
        }

        $this->attachFlags($modelInfo, $modelData['flags']);

        return $modelInfo;
    }
}
