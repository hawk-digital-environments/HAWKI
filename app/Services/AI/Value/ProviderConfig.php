<?php
declare(strict_types=1);


namespace App\Services\AI\Value;


use App\Services\AI\Exception\MissingKeyInProviderConfigException;

/**
 * An object representation of the AI model provider configuration in model_providers.php
 */
readonly class ProviderConfig implements \JsonSerializable
{
    public function __construct(
        private string $providerId,
        private array  $config
    )
    {
    }
    
    /**
     * Returns a value from the config array or a default value if the key does not exist.
     * @param string $key The key to look for in the config array.
     * @param mixed|null $default The default value to return if the key does not exist.
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Returns the provider ID.
     * The provider ID is the key used in the model_providers.php config file, it is unique in the system
     * and describes this provider. E.g. 'openai', 'azureOpenai', 'ollama', etc.
     * @return string
     */
    public function getId(): string
    {
        return $this->providerId;
    }
    
    /**
     * Returns the adapter name.
     * The adapter name is (if omitted) the same as the provider ID.
     * It is used to find the required adapter class for this provider.
     * This field is used to support multiple providers (e.g. with different configs) using the same adapter.
     * So it is possible to have multiple OpenAI providers with different API keys and settings.
     * @return string
     */
    public function getAdapterName(): string
    {
        return $this->get('adapter', $this->getId());
    }
    
    /**
     * Returns whether this provider is active or not.
     * Inactive providers are ignored when listing available models.
     * @return bool
     */
    public function isActive(): bool
    {
        return (bool)$this->get('active', false);
    }
    
    /**
     * Returns the API key for this provider or null if not set.
     * @return string|null
     */
    public function getApiKey(): ?string
    {
        $apiKey = $this->get('api_key');
        if (empty($apiKey)) {
            return null;
        }
        return $apiKey;
    }
    
    /**
     * Returns the API URL for this provider.
     * Throws an exception if the key is missing.
     * @return string
     */
    public function getApiUrl(): string
    {
        return $this->getOrFail('api_url');
    }
    
    /**
     * Returns the stream URL for this provider.
     * If not set, the API URL is returned.
     * @return string
     */
    public function getStreamUrl(): string
    {
        $streamUrl = $this->get('stream_url');
        if ($streamUrl) {
            return $streamUrl;
        }
        return $this->getApiUrl();
    }
    
    /**
     * Returns the ping URL for this provider or null if not set.
     * The ping URL is used to check if the provider is reachable and the API key is valid.
     * @return string|null
     */
    public function getPingUrl(): ?string
    {
        $pingUrl = $this->get('ping_url');
        if (empty($pingUrl)) {
            return null;
        }
        return $pingUrl;
    }
    
    /**
     * Returns the list of models available for this provider.
     * Throws an exception if the key is missing.
     * @return array[]
     */
    public function getModels(): array
    {
        return $this->getOrFail('models');
    }
    
    /**
     * Returns the full config array.
     * @return array
     */
    public function toArray(): array
    {
        return $this->config;
    }
    
    public function jsonSerialize(): array
    {
        return [
            'providerId' => $this->getId(),
            'config' => $this->toArray(),
        ];
    }
    
    private function getOrFail(string $key): mixed
    {
        if (empty($this->config[$key])) {
            throw new MissingKeyInProviderConfigException($this->getId(), $key);
        }
        
        return $this->config[$key];
    }
}
