<?php
declare(strict_types=1);


namespace App\Services\Ai\ProviderAdapters;


use App\Models\Ai\AiProvider;
use App\Services\Ai\ProviderAdapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\StatusCheck\ModelStatusFetcher;
use App\Services\Ai\Values\ParameterSource;
use NeuronAI\HttpClient\HttpClientInterface;
use NeuronAI\Tools\ProviderTool;

abstract class AbstractProviderAdapter implements ProviderAdapterInterface
{
    public function createHttpClient(ParameterSource $source): HttpClientInterface|null
    {
        $neuronProvider = $this->createNeuronProvider($source);

        if (!method_exists($neuronProvider, 'getHttpClient')) {
            return null;
        }

        $httpClient = $neuronProvider->getHttpClient();
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        if (!$httpClient instanceof HttpClientInterface) {
            return null;
        }

        return $httpClient;
    }

    public function supportsChat(): bool
    {
        // By default, all neuron providers support chat.
        return true;
    }

    public function supportsStreaming(): bool
    {
        // By default, all neuron providers support streaming.
        return true;
    }

    public function supportsImageGeneration(): bool
    {
        return false;
    }

    public function supportsTextToSpeech(): bool
    {
        return false;
    }

    public function supportsSpeechToText(): bool
    {
        return false;
    }

    public function checkModelStatus(AiModelOnlineStatusCollection $statusCollection, AiModelDemandCollection $demandCollection, ParameterSource $source): void
    {
        $statusCollection->setAllUnknown();
    }

    public function getNameLabel(): string|null
    {
        return null;
    }

    public function getDescriptionLabel(): string|null
    {
        return null;
    }

    public function getProviderToolForCapability(string $capability, ParameterSource $source): ProviderTool|null
    {
        return null;
    }

    protected function assertNonEmptyApiUrl(string|null $apiUrl, ParameterSource|AiProvider $source): string
    {
        if (empty($apiUrl)) {
            $provider = $source instanceof ParameterSource ? $source->getProvider() : $source;
            throw new \InvalidArgumentException(sprintf(
                'API URL is required for provider %s with adapter key %s. Please provide a valid API URL in the provider configuration.',
                $provider->name,
                $provider->adapter_key
            ));
        }

        return $apiUrl;
    }


    protected function createStatusFetcher(ParameterSource $source): ModelStatusFetcher
    {
        $client = $this->createHttpClient($source);
        if (!$client) {
            throw new \RuntimeException(sprintf(
                'Provider adapter %s does not support creating an HTTP client, which is required for status checks. Please implement createHttpClient() to return a valid HttpClientInterface instance.',
                static::class
            ));
        }
        return new ModelStatusFetcher($client, $source->getProvider()->model_status_url);
    }
}
