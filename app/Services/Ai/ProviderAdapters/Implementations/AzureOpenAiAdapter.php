<?php
declare(strict_types=1);


namespace App\Services\Ai\ProviderAdapters\Implementations;


use App\Models\Ai\AiProvider;
use App\Services\Ai\ProviderAdapters\AbstractProviderAdapter;
use App\Services\Ai\ProviderAdapters\Traits\OpenAiParameterTrait;
use App\Services\Ai\ProviderAdapters\Traits\OpenAiStatusCheckTrait;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\Values\ParameterSource;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\OpenAI\AzureOpenAI;

class AzureOpenAiAdapter extends AbstractProviderAdapter
{
    use OpenAiStatusCheckTrait;
    use OpenAiParameterTrait;

    public function createNeuronProvider(ParameterSource $source): AIProviderInterface
    {
        return new AzureOpenAI(
            key: $source->getProvider()->api_key,
            endpoint: $this->findEndpoint($source->getProvider()),
            model: $source->getModel()->model_id,
            version: $this->findVersion($source->getProvider()),
            /* @see https://learn.microsoft.com/en-us/azure/foundry/openai/reference#chat-completions */
            parameters: $this->buildOpenAiCompletionsParameters($source)
        );
    }

    public function checkModelStatus(AiModelOnlineStatusCollection $statusCollection, AiModelDemandCollection $demandCollection, ParameterSource $source): void
    {
        /* @see https://learn.microsoft.com/en-us/rest/api/azureopenai/models/list?view=rest-azureopenai-2024-10-21&tabs=HTTP */
        $this->runOpenAiStatusCheck($statusCollection, $this->createStatusFetcher($source), $this->findEndpoint($provider) . '/openai/models');
    }

    private function findVersion(AiProvider $provider): string
    {
        return $provider->additional_config['version'] ?? '2024-10-21';
    }

    private function findEndpoint(AiProvider $provider): string
    {
        return $this->assertNonEmptyApiUrl($provider->api_url, $provider);
    }
}
