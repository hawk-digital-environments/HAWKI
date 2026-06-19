<?php
declare(strict_types=1);


namespace App\Services\Ai\ProviderAdapters\Implementations;


use App\Services\Ai\ProviderAdapters\AbstractProviderAdapter;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\Values\ParameterSource;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Deepseek\Deepseek;

class DeepseekAdapter extends AbstractProviderAdapter
{
    public function createNeuronProvider(ParameterSource $source): AIProviderInterface
    {
        return new Deepseek(
            key: $source->getProvider()->api_key,
            model: $source->getModel()->model_id,
            /* @see https://api-docs.deepseek.com/api/create-chat-completion */
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
        /* @see https://api-docs.deepseek.com/api/list-models */
        foreach ($this->createStatusFetcher($source)->getExtract('/models', 'data.*.id') as $modelId) {
            $statusCollection->setOnline($modelId);
        }
    }
}
