<?php
declare(strict_types=1);


namespace App\Services\Ai\ProviderAdapters\Implementations;


use App\Services\Ai\ProviderAdapters\AbstractProviderAdapter;
use App\Services\Ai\ProviderAdapters\Traits\OpenAiParameterTrait;
use App\Services\Ai\ProviderAdapters\Traits\OpenAiStatusCheckTrait;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\Values\ParameterSource;
use App\Services\Ai\Values\WellKnownCapabilities;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\OpenAI\Responses\OpenAIResponses;
use NeuronAI\Tools\ProviderTool;

class OpenAiResponsesAdapter extends AbstractProviderAdapter
{
    use OpenAiStatusCheckTrait;
    use OpenAiParameterTrait;

    public function createNeuronProvider(ParameterSource $source): AIProviderInterface
    {
        return new OpenAIResponses(
            key: $source->getProvider()->api_key,
            model: $source->getModel()->model_id,
            parameters: $this->buildOpenAiResponsesParameters($source)
        );
    }

    public function checkModelStatus(AiModelOnlineStatusCollection $statusCollection, AiModelDemandCollection $demandCollection, ParameterSource $source): void
    {
        $this->runOpenAiStatusCheck($statusCollection, $this->createStatusFetcher($source));
    }

    public function getProviderToolForCapability(string $capability, ParameterSource $source): ProviderTool|null
    {
        return match ($capability) {
            /* @see https://developers.openai.com/api/docs/guides/tools-web-search */
            WellKnownCapabilities::WEB_SEARCH => ProviderTool::make(
                type: 'web_search',
                options: [
                    'external_web_access' => true,
                ]
            ),
            default => null
        };
    }
}
