<?php
declare(strict_types=1);


namespace App\Services\Ai\ProviderAdapters\Implementations;


use App\Services\Ai\ProviderAdapters\AbstractProviderAdapter;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\Values\ParameterSource;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\HuggingFace\HuggingFace;
use NeuronAI\Providers\HuggingFace\InferenceProvider;

class HuggingfaceAdapter extends AbstractProviderAdapter
{
    public function createNeuronProvider(ParameterSource $source): AIProviderInterface
    {
        $interferenceProvider = $source->getProvider()->settings->getAdapterSettings()['inference_provider'];
        return new HuggingFace(
            key: $source->getProvider()->api_key,
            model: $source->getModel()->model_id,
            inferenceProvider: !empty($interferenceProvider)
                ? InferenceProvider::from($$interferenceProvider)
                : InferenceProvider::HF_INFERENCE,
            /* @see https://huggingface.co/docs/inference-providers/tasks/chat-completion */
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
        // There is no API endpoint to check Huggingface model status,
        // if you find one, please open a PR to implement it. For now, we'll just assume all models are online.
        $statusCollection->setAllOnline();
    }
}
