<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Implementations;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Adapters\AbstractTextGeneratingAgent;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\LaravelAi\Drivers\GeminiExtended\ExtendedGeminiGateway;
use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Ai\Models\Flags\Values\WellKnownModelFlags;
use App\Services\Ai\Models\Parameters\Values\WellKnownModelParams;
use App\Services\Ai\Providers\Adapters\AbstractProviderAdapter;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Translation\LocaleService;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Gateway\Gemini\Concerns\CreatesGeminiClient;
use Laravel\Ai\Providers\GeminiProvider;
use Laravel\Ai\Providers\Provider as Driver;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\WebSearch;

/**
 * Provider adapter for Google Gemini.
 *
 * Uses an extended Gemini gateway ({@see ExtendedGeminiGateway}) that is wired through
 * the container builder pattern so the event dispatcher is injected automatically.
 *
 * Beyond driver creation and model discovery, this adapter:
 * - Injects thinking-budget and safety-setting options into every text-generating agent
 *   request via {@see getAdditionalDriverOptions()}.
 * - Exposes Gemini-native web search and URL-fetch tools via {@see getNativeToolFactoryForCapability()}.
 *
 * Model metadata is richer than most providers: the Gemini API returns token limits,
 * temperature/top-p defaults, and a description per model, all of which are stored
 * on the resulting {@see \App\Models\Ai\AiModel} instances.
 *
 * @see https://ai.google.dev/api/models#method:-models.list Gemini models API
 */
class GeminiAdapter extends AbstractProviderAdapter
{
    use CreatesGeminiClient;

    public function __construct(
        private readonly LocaleService $localeService
    )
    {
    }

    /**
     * Creates a Gemini driver using {@see ExtendedGeminiGateway} so that HAWKI's custom
     * gateway extensions (e.g. thinking-token tracking) are active for every request.
     */
    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
    {
        return $factory->make(
            driverName: Lab::Gemini,
            config: [
                'key' => $provider->api_key,
            ],
            builder: function (Dispatcher $dispatcher, array $config) {
                return new GeminiProvider(
                    gateway: new ExtendedGeminiGateway($dispatcher),
                    config: $config,
                    events: $dispatcher
                );
            }
        );
    }

    /**
     * Injects Gemini-specific generation config and safety settings for text-generating agents.
     *
     * The thinking budget is capped at half the total max-token allowance so that the model
     * cannot spend all its token budget on internal reasoning at the expense of the final answer.
     * Safety filtering is relaxed to "block only high" for dangerous content because HAWKI's
     * own content policy sits above the model layer.
     *
     * Only applies when $agent is a {@see AbstractTextGeneratingAgent}; returns an empty array
     * for other agent types (e.g. image-generation agents).
     */
    public function getAdditionalDriverOptions(Agent $agent, AgentRequestContext $context): array
    {
        if ($agent instanceof AbstractTextGeneratingAgent) {
            $maxTokens = $context->modelParameters->getMaxTokens();
            $maxThinkingTokens = $context->modelParameters->getMaxThinkingTokens();
            $maxThinkingTokensLimited = min($maxThinkingTokens, $maxTokens / 2);

            return [
                'generationConfig' => [
                    'topK' => 10,
                    'thinkingConfig' => [
                        'includeThoughts' => true,
                        'thinkingBudget' => $maxThinkingTokensLimited
                    ]
                ],
                'safetySettings' => [
                    [
                        'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                        'threshold' => 'BLOCK_ONLY_HIGH'
                    ]
                ]
            ];
        }

        return [];
    }

    /**
     * Fetches Gemini models from the `/models` endpoint and enriches each entry with
     * metadata the API provides directly.
     *
     * The model name arrives as `"models/gemini-..."` — the `models/` prefix is stripped
     * so the stored `model_id` matches the bare identifier used in API calls.
     *
     * Per-model enrichment applied when the API data is present:
     * - Reasoning flag ({@see WellKnownModelFlags::FEATURE_REASONING}) when `thinking` is true.
     * - Sampling-parameters flag ({@see WellKnownModelFlags::FEATURE_SAMPLING_PARAMETERS})
     *   when `temperature` or `top_p` is present.
     * - Default parameter values for temperature and top-p.
     * - English description attached to the model's description collection.
     * - Input/output token limits.
     *
     * @return Collection<int, \App\Models\Ai\AiModel>
     *
     * @see https://ai.google.dev/api/models#method:-models.list
     */
    public function getModels(AiProviderProxy $provider): Collection
    {
        /* @see https://ai.google.dev/api/models#method:-models.list */
        return $this->createModelListClient($this->client($provider->driver))
            ->get('/models')
            ->getMapped('models.*', function (array $model) use ($provider) {
                $id = data_get($model, 'name');
                if (str_starts_with($id, 'models/')) {
                    $id = substr($id, strlen('models/'));
                }

                $info = $this->createNewModelInfo(
                    modelId: $id,
                    provider: $provider,
                );

                if (data_get($model, 'thinking') === true) {
                    $this->attachFlags($info, [WellKnownModelFlags::FEATURE_REASONING]);
                }
                if (isset($model['temperature']) || isset($model['top_p'])) {
                    $this->attachFlags($info, [WellKnownModelFlags::FEATURE_SAMPLING_PARAMETERS]);
                }

                $this->enrichParameters(
                    $info,
                    array_filter(
                        [
                            WellKnownModelParams::TEMPERATURE => isset($model['temperature']) ? (float)$model['temperature'] : null,
                            WellKnownModelParams::TOP_P => isset($model['top_p']) ? (float)$model['top_p'] : null,
                        ]
                    )
                );

                $description = data_get($model, 'description');
                if (!empty($description)) {
                    $this->attachDescription($info, $this->localeService->getLocale('en'), $description);
                }

                $this->enrichChatLimits(
                    $info,
                    maxInputTokens: data_get($model, 'inputTokenLimit'),
                    maxOutputTokens: data_get($model, 'outputTokenLimit')
                );

                return $info;
            });
    }

    /**
     * Returns a factory for Gemini-native tool instances for the given capability.
     *
     * Gemini supports two native tool integrations directly on the API:
     * - `WEB_SEARCH` — Google Search grounding via {@see WebSearch}
     * - `WEB_FETCH`  — URL context fetching via {@see WebFetch}
     *
     * Returning a factory here causes HAWKI to use the provider's built-in tool instead
     * of dispatching HAWKI's own HTTP-based tool implementation for those capabilities.
     *
     * @see https://ai.google.dev/gemini-api/docs/google-search
     * @see https://ai.google.dev/gemini-api/docs/url-context
     */
    public function getNativeToolFactoryForCapability(string $capability): \Closure|null
    {
        return match ($capability) {
            WellKnownCapabilities::WEB_SEARCH => static fn() => new WebSearch(),
            WellKnownCapabilities::WEB_FETCH => static fn() => new WebFetch(),
            default => null
        };
    }
}
