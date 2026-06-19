<?php
declare(strict_types=1);


namespace App\Services\Ai\ProviderAdapters\Implementations;


use App\Services\Ai\ProviderAdapters\AbstractProviderAdapter;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\Values\ParameterSource;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Cohere\Cohere;

class CohereAdapter extends AbstractProviderAdapter
{
    public function createNeuronProvider(ParameterSource $source): AIProviderInterface
    {
        return new Cohere(
            key: $source->getProvider()->api_key,
            model: $source->getModel()->model_id,
            /* @see https://docs.cohere.com/reference/chat */
            parameters: array_merge(
                [
                    'temperature' => $source->getTemperature(0.3),
                    'p' => $source->getTopP(),
                    'max_tokens' => $source->getMaxTokens(),
                ],
                $source->toAdditionalArray()
            )
        );
    }

    public function checkModelStatus(AiModelOnlineStatusCollection $statusCollection, AiModelDemandCollection $demandCollection, ParameterSource $source): void
    {
        /* @see https://docs.cohere.com/reference/list-models */
        foreach ($this->createStatusFetcher($source)->getExtract('https://api.cohere.ai/v1/models', 'models.*.name') as $modelId) {
            $statusCollection->setOnline($modelId);
        }
    }
}
