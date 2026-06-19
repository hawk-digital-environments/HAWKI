<?php
declare(strict_types=1);


namespace App\Services\Ai\ProviderAdapters\Implementations;


use App\Services\Ai\ProviderAdapters\AbstractProviderAdapter;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\Values\ParameterSource;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Mistral\Mistral;

class MistralAdapter extends AbstractProviderAdapter
{
    public function createNeuronProvider(ParameterSource $source): AIProviderInterface
    {
        return new Mistral(
            key: $source->getProvider()->api_key,
            model: $source->getModel()->model_id,
            /* @see https://docs.mistral.ai/api/endpoint/chat#operation-chat_completion_v1_chat_completions_post */
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
        /* @see https://docs.mistral.ai/api/endpoint/models#operation-list_models_v1_models_get */
        foreach ($this->createStatusFetcher($source)->getExtract('/models', '*.id') as $modelId) {
            $statusCollection->setOnline($modelId);
        }
    }
}
