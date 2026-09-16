<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Implementations;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Adapters\AbstractTextGeneratingAgent;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Providers\Adapters\AbstractProviderAdapter;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Adapters\Values\AnthropicThinkingConfig;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Gateway\Anthropic\Concerns\CreatesAnthropicClient;
use Laravel\Ai\Providers\Provider as Driver;
use Psr\Log\LoggerInterface;


/**
 * Provider adapter for Anthropic (Claude models).
 *
 * Builds the Laravel AI Anthropic driver using the provider's stored API key and
 * fetches the available model list from the Anthropic REST API.
 *
 * @see https://platform.claude.com/docs/en/api/models/list Anthropic models API
 */
class AnthropicAdapter extends AbstractProviderAdapter
{
    use CreatesAnthropicClient;

    public function __construct(
        private readonly LoggerInterface $logger,
    )
    {
    }

    /**
     * Creates an Anthropic driver instance authenticated with the provider's API key.
     */
    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
    {
        return $factory->make(
            driverName: Lab::Anthropic,
            config: [
                'key' => $provider->api_key,
            ]
        );
    }

    /**
     * Enables Anthropic extended thinking for reasoning-capable text models.
     *
     * Budget and sampling decisions are made by {@see AnthropicThinkingConfig}; every
     * deviation from the user-configured parameters is logged as a warning.
     *
     * @see https://docs.anthropic.com/en/docs/build-with-claude/extended-thinking
     */
    public function getAdditionalDriverOptions(Agent $agent, AgentRequestContext $context): array
    {
        if (!$agent instanceof AbstractTextGeneratingAgent || !$context->model->flags->hasStrengthReasoning()) {
            return [];
        }

        $config = AnthropicThinkingConfig::from(
            requestedBudgetTokens: $context->modelParameters->getMaxThinkingTokens(),
            maxTokens: $agent->maxTokens() ?? 64_000,
            temperature: $agent->temperature(),
            topP: $agent->topP(),
        );

        $this->logWarnings($config);

        if (!$config->enabled) {
            return [];
        }

        return ['thinking' => $config->thinking] + $config->samplingOverrides;
    }

    private function logWarnings(AnthropicThinkingConfig $config): void
    {
        foreach ($config->warnings as $warning) {
            $this->logger->warning($warning);
        }
    }

    /**
     * Fetches available Claude models from the Anthropic API.
     *
     * The response shape is `{ "data": [ { "id": "claude-...", … }, … ] }`.
     * Each entry is mapped to an unsaved {@see \App\Models\Ai\AiModel} instance.
     *
     * @return Collection<int, \App\Models\Ai\AiModel>
     *
     * @see https://platform.claude.com/docs/en/api/models/list
     */
    public function getModels(AiProviderProxy $provider): Collection
    {
        return $this->createModelListClient($this->client($provider->driver))
            ->get('/models')
            ->getMapped('data.*', function ($item) use ($provider) {
                return $this->createNewModelInfo(
                    modelId: data_get($item, 'id'),
                    provider: $provider,
                );
            });
    }
}
