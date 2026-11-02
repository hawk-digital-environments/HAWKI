<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation\Enrichment\Implementations;


use App\Models\Ai\AiModel;
use App\Services\Ai\ModelInformation\Enrichment\Contracts\ModelInfoEnricherInterface;
use App\Services\Ai\ModelInformation\Enrichment\ModelInfoEnrichingTrait;
use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Ai\Providers\Adapters\Implementations\GeminiAdapter;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Utils\JobMetrics;

/**
 * Enricher that applies handcrafted knowledge to models served by the Google Gemini API.
 *
 * The Gemini `/models` endpoint does not expose every API quirk or tool support of a model.
 * Known facts are maintained manually in the static DATA array, keyed by model ID, and read
 * back by {@see GeminiAdapter} when building the request.
 *
 * Native capabilities (Google Search grounding and URL context) are granted to the models that
 * support them. Probed against the API (2026-11): Gemma accepts the `googleSearch` tool but
 * silently ignores it, and the TTS models reject it — both get nothing.
 *
 * Only models of a provider using the {@see GeminiAdapter} are affected, so the same model ID
 * served by another provider (e.g. Gemma on GWDG) is left untouched.
 */
class StaticGoogleEnricher implements ModelInfoEnricherInterface
{
    use ModelInfoEnrichingTrait;

    private const array GROUNDING_CAPABILITIES = [
        WellKnownCapabilities::WEB_SEARCH,
        WellKnownCapabilities::WEB_FETCH,
    ];

    private const array DATA = [
        'gemini-4-argon' => [
            'native_capabilities' => self::GROUNDING_CAPABILITIES,
        ],
        'gemini-3.8-flash' => [
            'native_capabilities' => self::GROUNDING_CAPABILITIES,
        ],
        'gemini-3.6-flash' => [
            'native_capabilities' => self::GROUNDING_CAPABILITIES,
        ],
        'gemini-3.5-flash-lite' => [
            'native_capabilities' => self::GROUNDING_CAPABILITIES,
        ],
        'gemini-3.1-flash-lite' => [
            'native_capabilities' => self::GROUNDING_CAPABILITIES,
        ],
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
     * Looks up the model's ID in the static DATA table and attaches its flags and native capabilities.
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

        $this->attachFlags($modelInfo, $modelData['flags'] ?? []);
        $this->enrichNativeCapabilities($modelInfo, $modelData['native_capabilities'] ?? null);

        return $modelInfo;
    }
}
