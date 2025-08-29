<?php

namespace App\Services\AI;

use App\Services\AI\Interfaces\AIModelProviderInterface;
use App\Services\AI\Providers\OpenAI\OpenAIProvider;
use App\Services\AI\Providers\GWDG\GWDGProvider;
use App\Services\AI\Providers\Google\GoogleProvider;
use App\Services\AI\Providers\Ollama\OllamaProvider;
use App\Services\AI\Providers\OpenWebUI\OpenWebUIProvider;

use Illuminate\Support\Facades\Log;

class AIProviderFactory
{
    /**
     * Configuration from model_providers.php
     *
     * @var array
     */
    private $config;

    /**
     * Create a new provider factory
     *
     * @param array $config
     */
    public function __construct()
    {
        $this->config = config('model_providers');
    }

    /**
     * Get the appropriate provider for a model
     *
     * @param string $modelId
     * @return AIModelProviderInterface
     * @throws \Exception
     */
    public function getProviderForModel(string $modelId): AIModelProviderInterface
    {
        $providerId = $this->getProviderId($modelId);
        return $this->getProviderInterface($providerId);
    }

    public function getProviderInterface(string $providerId): AIModelProviderInterface
    {
        switch ($providerId) {
            case 'openai':
                return new OpenAIProvider($this->config['providers']['openai']);
            case 'gwdg':
                return new GWDGProvider($this->config['providers']['gwdg']);
            case 'google':
                return new GoogleProvider($this->config['providers']['google']);
            case 'ollama':
                return new OllamaProvider($this->config['providers']['ollama']);
            case 'openWebUi':
                return new OpenWebUIProvider($this->config['providers']['openWebUi']);
            default:
                throw new \Exception("Unsupported provider: {$providerId}");
        }
    }

    /**
     * Determine the provider ID based on the model ID
     *
     * @param string $modelId
     * @return string
     * @throws \Exception
     */
    private function getProviderId(string $modelId): string
    {
        foreach ($this->config['providers'] as $providerId => $provider) {
            if (!$provider['active']) continue;

            foreach ($provider['models'] as $model) {
                if ($model['id'] === $modelId) {
                    return $providerId;
                }
            }
        }

        throw new \Exception("Unknown model ID: {$modelId}");
    }
}
