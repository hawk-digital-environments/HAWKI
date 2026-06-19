<?php
declare(strict_types=1);


namespace App\Services\Ai\ProviderAdapters\Implementations;


use App\Services\Ai\ProviderAdapters\AbstractProviderAdapter;
use App\Services\Ai\ProviderAdapters\Traits\OpenAiParameterTrait;
use App\Services\Ai\ProviderAdapters\Traits\OpenAiStatusCheckTrait;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\Values\ParameterSource;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\OpenAILike;

class OpenAiLikeAdapter extends AbstractProviderAdapter
{
    use OpenAiParameterTrait;
    use OpenAiStatusCheckTrait;

    protected string|null $baseUrl = null;

    public function createNeuronProvider(ParameterSource $source): AIProviderInterface
    {
        $baseUri = $this->baseUrl ?? $source->getProvider()->api_url;
        return new OpenAILike(
            baseUri: $this->assertNonEmptyApiUrl($baseUri, $source),
            key: $source->getProvider()->api_key,
            model: $source->getModel()->model_id,
            parameters: $this->buildOpenAiCompletionsParameters($source)
        );
    }

    public function checkModelStatus(AiModelOnlineStatusCollection $statusCollection, AiModelDemandCollection $demandCollection, ParameterSource $source): void
    {
        $this->runOpenAiStatusCheck($statusCollection, $this->createStatusFetcher($source));
    }
}
