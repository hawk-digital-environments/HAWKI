<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Implementations;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Adapters\AbstractTextGeneratingAgent;
use App\Services\Ai\Agents\Adapters\LaravelChatAgent;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\LaravelAi\Drivers\GeminiExtended\ExtendedGeminiGateway;
use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Ai\Models\Flags\Values\WellKnownModelFlags;
use App\Services\Ai\Models\Parameters\Values\WellKnownModelParams;
use App\Services\Ai\Providers\Adapters\AbstractProviderAdapter;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Translation\LocaleService;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Gateway\Gemini\Concerns\CreatesGeminiClient;
use Laravel\Ai\Providers\GeminiProvider;
use Laravel\Ai\Providers\Provider as Driver;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\WebSearch;

class GeminiAdapter extends AbstractProviderAdapter
{
    use CreatesGeminiClient;

    public function __construct(
        private readonly LocaleService $localeService
    )
    {
    }

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

    public function createHttpClient(AiProviderProxy $provider): PendingRequest
    {
        return $this->client($provider->driver);
    }

    /**
     * @inheritDoc
     */
    public function getModels(AiProviderProxy $provider): Collection
    {
        /* @see https://ai.google.dev/api/models#method:-models.list */
        return $this->createModelListClient($provider)
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

    public function checkModelStatus(
        AiModelOnlineStatusCollection $statusCollection,
        AiModelDemandCollection       $demandCollection,
        AiProviderProxy               $provider
    ): void
    {
        /* @see https://ai.google.dev/api/models#method:-models.list */
        foreach ($this->createModelListClient($provider)->get('/models')->getList('models.*.name') as $modelId) {
            $statusCollection->setOnline($modelId);
        }
    }

    public function getNativeToolFactoryForCapability(string $capability): \Closure|null
    {
        return match ($capability) {
            /* @see https://ai.google.dev/gemini-api/docs/google-search */
            WellKnownCapabilities::WEB_SEARCH => static fn() => new WebSearch(),
            /* @see https://ai.google.dev/gemini-api/docs/url-context */
            WellKnownCapabilities::WEB_FETCH => static fn() => new WebFetch(),
            default => null
        };
    }
}
