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
     * Mapping of model IDs to provider names
     * 
     * @var array
     */
    private $modelProviderMap = [];
    
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
            $models = LanguageModel::select('language_models.model_id', 'provider_settings.provider_name')
                ->join('provider_settings', 'language_models.provider_id', '=', 'provider_settings.id')
                ->where('language_models.is_active', true)
                ->where('provider_settings.is_active', true)
                ->get();
            
            foreach ($models as $model) {
                $this->modelProviderMap[$model->model_id] = $model->provider_name;
            }
        } catch (\Exception $e) {
            Log::error('Failed to load model-provider mappings: ' . $e->getMessage());
            // Fallback to an empty array if the database is not ready yet
            $this->modelProviderMap = [];
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
        if (empty($this->modelProviderMap)) {
            // If the map is empty (e.g. on first run), try loading again
            $this->loadModelProviderMappings();
        }
        
        // Check if we know the provider for this model
        if (!isset($this->modelProviderMap[$modelId])) {
            Log::error("Unknown model ID: $modelId");
            throw new \Exception("Unknown model ID: $modelId");
        }
        
        $providerName = $this->modelProviderMap[$modelId];
        return $this->getProviderInterface($providerName);
    }
    
    /**
     * Get a provider interface by provider name
     * 
     * @param string $providerName
     * @return \App\Services\AI\Interfaces\AIModelProviderInterface
     * @throws \Exception
     */
    public function getProviderInterface(string $providerName)
    {
        // If we already have an instance, return it
        if (isset($this->providerInstances[$providerName])) {
            return $this->providerInstances[$providerName];
        }
        
        try {
            // Get the provider settings from the database
            $provider = ProviderSetting::where('provider_name', $providerName)
                ->where('is_active', true)
                ->first();
            
            if (!$provider) {
                throw new \Exception("Provider not found or not active: $providerName");
            }
            
            // Create the provider class based on the provider name
            $providerClass = $this->getProviderClass($providerName);
            
            if (!class_exists($providerClass)) {
                throw new \Exception("Provider class not found: $providerClass");
            }
            
            // Create an instance of the provider with the settings from the database
            $this->providerInstances[$providerName] = new $providerClass([
                'api_key' => $provider->api_key,
                'base_url' => $provider->base_url,
                'ping_url' => $provider->ping_url,
                'api_format' => $provider->api_format,
                'additional_settings' => $provider->additional_settings ? json_decode($provider->additional_settings, true) : []
            ]);
            
            return $this->providerInstances[$providerName];
        } catch (\Exception $e) {
            Log::error("Failed to create provider instance for $providerName: " . $e->getMessage());
            throw new \Exception("Failed to create provider instance: " . $e->getMessage());
        }
    }
    
    /**
     * Get the provider class name based on provider ID
     * 
     * @param string $providerName
     * @return string Fully qualified class name
     */
    private function getProviderClass(string $providerName)
    {
        // Map of provider names to provider classes
        // This could be moved to the database in the future
        $providerClasses = [
            'openai' => 'App\Services\AI\Providers\OpenAIProvider',
            'gwdg' => 'App\Services\AI\Providers\GWDGProvider',
            'openWebUi' => 'App\Services\AI\Providers\OpenWebUIProvider',
            'google' => 'App\Services\AI\Providers\GoogleProvider',
            'ollama' => 'App\Services\AI\Providers\OllamaProvider',

            // Add more providers here
        ];
        
        // Fallback to the generic implementation
        return $providerClasses[$providerName] ?? 'App\Services\AI\Providers\GenericProvider';
    }
    
    /**
     * Refresh model-provider mappings from database
     */
    public function refreshMappings()
    {
        $this->loadModelProviderMappings();
    }
}