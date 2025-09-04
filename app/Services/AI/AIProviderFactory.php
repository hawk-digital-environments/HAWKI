<?php

namespace App\Services\AI;

use App\Models\LanguageModel;
use App\Models\ProviderSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AIProviderFactory
{
    /**
     * Cache for provider instances
     *
     * @var array
     */
    private $providerInstances = [];

    /**
     * Mapping of model IDs to provider IDs
     *
     * @var array
     */
    private $modelProviderIdMap = [];

    /**
     * Cache duration for provider mappings (30 minutes)
     */
    const MAPPING_CACHE_TTL = 1800;

    /**
     * Cache duration for provider instances (1 hour)
     */
    const INSTANCE_CACHE_TTL = 3600;

    /**
     * Constructor - loads model-provider mappings from database with caching
     */
    public function __construct()
    {
        $this->loadModelProviderMappings();
    }

    /**
     * Load model to provider mappings from the database with caching
     */
    private function loadModelProviderMappings()
    {
        $cacheKey = 'ai_model_provider_mappings';

        $this->modelProviderIdMap = Cache::remember($cacheKey, self::MAPPING_CACHE_TTL, function () {
            try {
                // Load all active models and their providers from the database
                $models = LanguageModel::select('language_models.model_id', 'language_models.provider_id')
                    ->join('provider_settings', 'language_models.provider_id', '=', 'provider_settings.id')
                    ->where('language_models.is_active', true)
                    ->where('provider_settings.is_active', true)
                    ->get();

                $mappings = [];
                foreach ($models as $model) {
                    // Store the actual provider ID for each model
                    $mappings[$model->model_id] = $model->provider_id;
                }

                return $mappings;
            } catch (\Exception $e) {
                Log::error('Failed to load model-provider mappings: '.$e->getMessage());

                // Fallback to an empty array if the database is not ready yet
                return [];
            }
        });
    }

    /**
     * Get the provider interface for a specific model
     *
     * @return \App\Services\AI\Interfaces\AIModelProviderInterface
     *
     * @throws \Exception
     */
    public function getProviderForModel(string $modelId)
    {
        if (empty($this->modelProviderIdMap)) {
            // If the map is empty (e.g. on first run), try loading again
            $this->loadModelProviderMappings();
        }

        // Check if we know the provider ID for this model
        if (! isset($this->modelProviderIdMap[$modelId])) {
            Log::error("Unknown model ID: $modelId");
            throw new \Exception("Unknown model ID: $modelId");
        }

        $providerId = $this->modelProviderIdMap[$modelId];

        return $this->getProviderInterfaceById($providerId);
    }

    /**
     * Get a provider interface by provider ID (database ID) with caching
     *
     * @return \App\Services\AI\Interfaces\AIModelProviderInterface
     *
     * @throws \Exception
     */
    public function getProviderInterfaceById(int $providerId)
    {
        // If we already have an instance, return it
        if (isset($this->providerInstances[$providerId])) {
            return $this->providerInstances[$providerId];
        }

        // Try to get from cache first
        $cacheKey = "provider_instance_{$providerId}";

        try {
            // Get the provider settings from the database with API format relationship
            $provider = Cache::remember("provider_data_{$providerId}", self::INSTANCE_CACHE_TTL, function () use ($providerId) {
                return ProviderSetting::with('apiFormat')
                    ->where('id', $providerId)
                    ->where('is_active', true)
                    ->first();
            });

            if (! $provider) {
                throw new \Exception("Provider not found or not active with ID: $providerId");
            }

            // Use the API format unique_name for class selection, fall back to provider_name
            $apiFormat = $provider->apiFormat ? $provider->apiFormat->unique_name : $provider->provider_name;

            // Create the provider class based on the API format
            $providerClass = $this->getProviderClass($apiFormat);

            if (! class_exists($providerClass)) {
                throw new \Exception("Provider class not found: $providerClass");
            }

            // Create an instance of the provider with the settings from the database
            // Note: URLs are now generated dynamically by the ProviderSetting model using cached accessor methods
            $this->providerInstances[$providerId] = new $providerClass([
                'api_key' => $provider->api_key,
                'provider_name' => $provider->provider_name,
                'api_format' => $apiFormat,
                'provider_id' => $provider->id,
                'additional_settings' => is_string($provider->additional_settings)
                    ? json_decode($provider->additional_settings, true)
                    : ($provider->additional_settings ?? []),
            ]);

            return $this->providerInstances[$providerId];
        } catch (\Exception $e) {
            Log::error("Failed to create provider instance for provider ID $providerId: ".$e->getMessage());
            throw new \Exception('Failed to create provider instance: '.$e->getMessage());
        }
    }

    /**
     * Get a provider interface by API format (legacy method for backwards compatibility)
     *
     * @return \App\Services\AI\Interfaces\AIModelProviderInterface
     *
     * @throws \Exception
     */
    public function getProviderInterface(string $apiFormat)
    {
        try {
            // Find the first active provider with this API format through the relationship
            $provider = ProviderSetting::with('apiFormat')
                ->whereHas('apiFormat', function ($query) use ($apiFormat) {
                    $query->where('unique_name', $apiFormat);
                })
                ->where('is_active', true)
                ->first();

            // If no provider found with api_format relationship, fall back to provider_name search
            if (! $provider) {
                $provider = ProviderSetting::where('provider_name', $apiFormat)
                    ->where('is_active', true)
                    ->first();
            }

            if (! $provider) {
                throw new \Exception("Provider not found or not active for API format: $apiFormat");
            }

            return $this->getProviderInterfaceById($provider->id);
        } catch (\Exception $e) {
            Log::error("Failed to create provider instance for API format $apiFormat: ".$e->getMessage());
            throw new \Exception('Failed to create provider instance: '.$e->getMessage());
        }
    }

    /**
     * Get the provider class name based on API format
     *
     * @return string Fully qualified class name
     */
    private function getProviderClass(string $apiFormat)
    {
        // Map of API formats to provider classes
        // Using unique_name values from the database
        $providerClasses = [
            'openai-api' => 'App\Services\AI\Providers\OpenAIProvider',
            'ollama-api' => 'App\Services\AI\Providers\OllamaProvider',
            'google-generative-language-api' => 'App\Services\AI\Providers\GoogleProvider',
            'google-vertex-ai-api' => 'App\Services\AI\Providers\GoogleProvider',
            'gwdg-api' => 'App\Services\AI\Providers\GWDGProvider',
            'openwebui-api' => 'App\Services\AI\Providers\OpenWebUIProvider',
            'anthropic-api' => 'App\Services\AI\Providers\AnthropicProvider',
            'huggingface-api' => 'App\Services\AI\Providers\OpenAIProvider', // Uses OpenAI-compatible interface
            'cohere-api' => 'App\Services\AI\Providers\OpenAIProvider', // Uses OpenAI-compatible interface

            // Legacy support for old provider names
            'openai' => 'App\Services\AI\Providers\OpenAIProvider',
            'gwdg' => 'App\Services\AI\Providers\GWDGProvider',
            'openWebUi' => 'App\Services\AI\Providers\OpenWebUIProvider',
            'google' => 'App\Services\AI\Providers\GoogleProvider',
            'ollama' => 'App\Services\AI\Providers\OllamaProvider',
            'anthropic' => 'App\Services\AI\Providers\AnthropicProvider',
            'hawki' => 'App\Services\AI\Providers\HAWKIProvider',
        ];

        // Fallback to OpenAI provider for unknown formats (most compatible)
        return $providerClasses[$apiFormat] ?? 'App\Services\AI\Providers\OpenAIProvider';
    }

    /**
     * Refresh model-provider mappings from database
     */
    public function refreshMappings()
    {
        // Clear the cache and reload
        Cache::forget('ai_model_provider_mappings');
        $this->loadModelProviderMappings();

        // Also clear provider data cache
        foreach (array_keys($this->providerInstances) as $providerId) {
            Cache::forget("provider_data_{$providerId}");
        }

        // Clear local instances cache
        $this->providerInstances = [];
    }

    /**
     * Clear all cached data for this factory
     */
    public function clearAllCaches(): void
    {
        // Clear mappings cache
        Cache::forget('ai_model_provider_mappings');

        // Clear all provider data caches
        foreach (array_keys($this->providerInstances) as $providerId) {
            Cache::forget("provider_data_{$providerId}");
        }

        // Clear local caches
        $this->modelProviderIdMap = [];
        $this->providerInstances = [];

        // Reload fresh data
        $this->loadModelProviderMappings();
    }

    /**
     * Get cache statistics for debugging
     */
    public function getCacheStats(): array
    {
        return [
            'mappings_cached' => count($this->modelProviderIdMap),
            'instances_cached' => count($this->providerInstances),
            'mapping_cache_key' => 'ai_model_provider_mappings',
            'cache_ttl_mappings' => self::MAPPING_CACHE_TTL,
            'cache_ttl_instances' => self::INSTANCE_CACHE_TTL,
        ];
    }
}
