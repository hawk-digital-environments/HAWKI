<?php

namespace App\Services\AI;

use App\Models\LanguageModel;
use App\Models\ProviderSetting;
use App\Services\AI\Interfaces\AIModelProviderInterface;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\AI\Providers\GWDGProvider;
use App\Services\AI\Providers\GoogleProvider;
use App\Services\AI\Providers\OllamaProvider;
use App\Services\AI\Providers\OpenWebUIProvider;
use App\Services\AI\Providers\HAWKIProvider;

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
     * Constructor - loads model-provider mappings from database
     */
    public function __construct()
    {
        $this->loadModelProviderMappings();
    }
    
    /**
     * Load model to provider mappings from the database
     */
    private function loadModelProviderMappings()
    {
        try {
            // Load all active models and their providers from the database
            $models = LanguageModel::select('language_models.model_id', 'language_models.provider_id')
                ->join('provider_settings', 'language_models.provider_id', '=', 'provider_settings.id')
                ->where('language_models.is_active', true)
                ->where('provider_settings.is_active', true)
                ->get();
            
            foreach ($models as $model) {
                // Store the actual provider ID for each model
                $this->modelProviderIdMap[$model->model_id] = $model->provider_id;
            }
        } catch (\Exception $e) {
            Log::error('Failed to load model-provider mappings: ' . $e->getMessage());
            // Fallback to an empty array if the database is not ready yet
            $this->modelProviderIdMap = [];
        }
    }
    
    /**
     * Get the provider interface for a specific model
     * 
     * @param string $modelId
     * @return \App\Services\AI\Interfaces\AIModelProviderInterface
     * @throws \Exception
     */
    public function getProviderForModel(string $modelId)
    {
        if (empty($this->modelProviderIdMap)) {
            // If the map is empty (e.g. on first run), try loading again
            $this->loadModelProviderMappings();
        }
        
        // Check if we know the provider ID for this model
        if (!isset($this->modelProviderIdMap[$modelId])) {
            Log::error("Unknown model ID: $modelId");
            throw new \Exception("Unknown model ID: $modelId");
        }
        
        $providerId = $this->modelProviderIdMap[$modelId];
        return $this->getProviderInterfaceById($providerId);
    }
    
    /**
     * Get a provider interface by provider ID (database ID)
     * 
     * @param int $providerId
     * @return \App\Services\AI\Interfaces\AIModelProviderInterface
     * @throws \Exception
     */
    public function getProviderInterfaceById(int $providerId)
    {
        // If we already have an instance, return it
        if (isset($this->providerInstances[$providerId])) {
            return $this->providerInstances[$providerId];
        }
        
        try {
            // Get the provider settings from the database with API format relationship
            $provider = ProviderSetting::with('apiFormat')
                ->where('id', $providerId)
                ->where('is_active', true)
                ->first();
            
            if (!$provider) {
                throw new \Exception("Provider not found or not active with ID: $providerId");
            }
            
            // Use the API format unique_name for class selection, fall back to provider_name
            $apiFormat = $provider->apiFormat ? $provider->apiFormat->unique_name : $provider->provider_name;
            
            // Create the provider class based on the API format
            $providerClass = $this->getProviderClass($apiFormat);
            
            if (!class_exists($providerClass)) {
                throw new \Exception("Provider class not found: $providerClass");
            }
            
            // Create an instance of the provider with the settings from the database
            // Note: URLs are now generated dynamically by the ProviderSetting model using accessor methods
            $this->providerInstances[$providerId] = new $providerClass([
                'api_key' => $provider->api_key,
                'provider_name' => $provider->provider_name,
                'api_format' => $apiFormat,
                'provider_id' => $provider->id,
                'additional_settings' => is_string($provider->additional_settings)
                    ? json_decode($provider->additional_settings, true)
                    : ($provider->additional_settings ?? [])
            ]);
            
            return $this->providerInstances[$providerId];
        } catch (\Exception $e) {
            Log::error("Failed to create provider instance for provider ID $providerId: " . $e->getMessage());
            throw new \Exception("Failed to create provider instance: " . $e->getMessage());
        }
    }
    
    /**
     * Get a provider interface by API format (legacy method for backwards compatibility)
     * 
     * @param string $apiFormat
     * @return \App\Services\AI\Interfaces\AIModelProviderInterface
     * @throws \Exception
     */
    public function getProviderInterface(string $apiFormat)
    {
        try {
            // Find the first active provider with this API format through the relationship
            $provider = ProviderSetting::with('apiFormat')
                ->whereHas('apiFormat', function($query) use ($apiFormat) {
                    $query->where('unique_name', $apiFormat);
                })
                ->where('is_active', true)
                ->first();
            
            // If no provider found with api_format relationship, fall back to provider_name search
            if (!$provider) {
                $provider = ProviderSetting::where('provider_name', $apiFormat)
                    ->where('is_active', true)
                    ->first();
            }
            
            if (!$provider) {
                throw new \Exception("Provider not found or not active for API format: $apiFormat");
            }
            
            return $this->getProviderInterfaceById($provider->id);
        } catch (\Exception $e) {
            Log::error("Failed to create provider instance for API format $apiFormat: " . $e->getMessage());
            throw new \Exception("Failed to create provider instance: " . $e->getMessage());
        }
    }
    
    /**
     * Get the provider class name based on API format
     * 
     * @param string $apiFormat
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
            'anthropic-api' => 'App\Services\AI\Providers\OpenAIProvider', // Uses OpenAI-compatible interface
            'huggingface-api' => 'App\Services\AI\Providers\OpenAIProvider', // Uses OpenAI-compatible interface
            'cohere-api' => 'App\Services\AI\Providers\OpenAIProvider', // Uses OpenAI-compatible interface
            
            // Legacy support for old provider names
            'openai' => 'App\Services\AI\Providers\OpenAIProvider',
            'gwdg' => 'App\Services\AI\Providers\GWDGProvider',
            'openWebUi' => 'App\Services\AI\Providers\OpenWebUIProvider',
            'google' => 'App\Services\AI\Providers\GoogleProvider',
            'ollama' => 'App\Services\AI\Providers\OllamaProvider',
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
        $this->loadModelProviderMappings();
    }
}