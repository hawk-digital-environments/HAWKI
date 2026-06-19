<?php
declare(strict_types=1);


namespace App\Services\Ai\ProviderAdapters\Implementations;


use App\Services\Ai\ProviderAdapters\AbstractProviderAdapter;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\Values\ParameterSource;
use App\Utils\Arrays\RecursiveMerger;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Ollama\Ollama;

class OllamaAdapter extends AbstractProviderAdapter
{
    public function createNeuronProvider(ParameterSource $source): AIProviderInterface
    {
        return new Ollama(
            url: $this->assertNonEmptyApiUrl($source->getProvider()->api_url, $source),
            model: $source->getModel()->model_id,
            /* @see https://docs.ollama.com/api/chat */
            parameters: RecursiveMerger::merge(
                [
                    'options' => [
                        'temperature' => $source->getTemperature(),
                        'top_p' => $source->getTopP(),
                        'num_predict' => $source->getMaxTokens(),
                    ]
                ],
                $source->toAdditionalArray()
            )
        );
    }

    public function checkModelStatus(AiModelOnlineStatusCollection $statusCollection, AiModelDemandCollection $demandCollection, ParameterSource $source): void
    {
        /* @see https://docs.ollama.com/api/ps */
        foreach ($this->createStatusFetcher($source)->getExtract('/ps', 'models.*.model') as $modelId) {
            $statusCollection->setOnline($modelId);
        }
    }
}
