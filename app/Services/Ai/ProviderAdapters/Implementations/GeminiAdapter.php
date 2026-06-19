<?php
declare(strict_types=1);


namespace App\Services\Ai\ProviderAdapters\Implementations;


use App\Services\Ai\ProviderAdapters\AbstractProviderAdapter;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\Values\ParameterSource;
use App\Services\Ai\Values\WellKnownCapabilities;
use App\Utils\Arrays\RecursiveMerger;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Tools\ProviderTool;

class GeminiAdapter extends AbstractProviderAdapter
{
    public function createNeuronProvider(ParameterSource $source): AIProviderInterface
    {
        // Ensure that the max thinking tokens does not exceed half of the total max tokens to prevent over-allocation to the thinking phase.
        $maxTokens = $source->getMaxTokens();
        $maxThinkingTokens = $source->getMaxThinkingTokens();
        $maxThinkingTokensLimited = min($maxThinkingTokens, $maxTokens / 2);

        return new Gemini(
            key: $source->getProvider()->api_key,
            model: $source->getModel()->model_id,
            /* @see https://ai.google.dev/api/generate-content#v1beta.GenerationConfig */
            parameters: RecursiveMerger::merge(
                [
                    'generationConfig' => [
                        'temperature' => $source->getTemperature(),
                        'maxOutputTokens' => $maxTokens,
                        'topP' => $source->getTopP(0.8),
                        'topK' => 10,
                        'thinkingConfig' => [
                            'includeThoughts' => true,
                            'thinkingBudget' => $maxThinkingTokensLimited
                        ]
                    ],
                ],
                $source->toAdditionalArray()
            )
        );
    }

    public function checkModelStatus(AiModelOnlineStatusCollection $statusCollection, AiModelDemandCollection $demandCollection, ParameterSource $source): void
    {
        /* @see https://ai.google.dev/api/models#method:-models.list */
        foreach ($this->createStatusFetcher($source)->getExtract('/', 'models.*.name') as $modelId) {
            $statusCollection->setOnline($modelId);
        }
    }

    public function getProviderToolForCapability(string $capability, ParameterSource $source): ProviderTool|null
    {
        return match ($capability) {
            /* @see https://ai.google.dev/gemini-api/docs/google-search */
            WellKnownCapabilities::WEB_SEARCH => ProviderTool::make(
                type: 'google_search'
            ),
            default => null
        };
    }
}
