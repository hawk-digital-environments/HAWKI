<?php
declare(strict_types=1);


namespace App\Services\Ai\ProviderAdapters\Implementations;


use App\Services\Ai\ProviderAdapters\AbstractProviderAdapter;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\Values\ParameterSource;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;


class AnthropicAdapter extends AbstractProviderAdapter
{
    public function createNeuronProvider(ParameterSource $source): AIProviderInterface
    {
        return new Anthropic(
            key: $source->getProvider()->api_key,
            model: $source->getModel()->model_id,
            version: $source->getProvider()->settings->getAdapterSettings()['version'] ?? null,
            max_tokens: $source->getMaxTokens(),
            /* @see https://platform.claude.com/docs/en/api/messages/create */
            parameters: array_merge(
                [
                    'temperature' => $source->getTemperature(),
                    'top_p' => $source->getTopP(),
                    'max_tokens' => $source->getMaxTokens(),
                ],
                $source->toAdditionalArray()
            )
        );
    }

    public function checkModelStatus(AiModelOnlineStatusCollection $statusCollection, AiModelDemandCollection $demandCollection, ParameterSource $source): void
    {
        /* @see https://platform.claude.com/docs/en/api/models/list */
        foreach ($this->createStatusFetcher($source)->getExtract('/models', 'data.*.id') as $modelId) {
            $statusCollection->setOnline($modelId);
        }
    }
}
