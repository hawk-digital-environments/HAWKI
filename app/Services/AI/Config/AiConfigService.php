<?php

declare(strict_types=1);

namespace App\Services\AI\Config;

use App\Models\AiAssistant;
use App\Models\AiModel;
use App\Models\ApiProvider;
use App\Models\ApiFormatEndpoint;
use App\Services\AI\Value\ModelUsageType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * Service for handling AI configuration from both config files and database
 * Switches between config-based and database-based AI configuration
 */
class AiConfigService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_KEY_DEFAULT_MODELS = 'ai_config_default_models';
    private const CACHE_KEY_SYSTEM_MODELS = 'ai_config_system_models';
    private const CACHE_KEY_PROVIDERS = 'ai_config_providers';

    /**
     * Get default models for the given usage type
     *
     * @param ModelUsageType|null $usageType
     * @return array
     */
    public function getDefaultModels(?ModelUsageType $usageType = null): array
    {
        $usageType = $usageType ?? ModelUsageType::DEFAULT;
        
        if ($this->useDatabaseConfig()) {
            return $this->getDefaultModelsFromDatabase($usageType);
        }

        return $this->getDefaultModelsFromConfig($usageType);
    }

    /**
     * Get system models for the given usage type
     *
     * @param ModelUsageType|null $usageType
     * @return array
     */
    public function getSystemModels(?ModelUsageType $usageType = null): array
    {
        $usageType = $usageType ?? ModelUsageType::DEFAULT;
        
        if ($this->useDatabaseConfig()) {
            return $this->getSystemModelsFromDatabase($usageType);
        }

        return $this->getSystemModelsFromConfig($usageType);
    }

    /**
     * Get provider configurations
     *
     * @return array
     */
    public function getProviders(): array
    {
        if ($this->useDatabaseConfig()) {
            return $this->getProvidersFromDatabase();
        }

        return $this->getProvidersFromConfig();
    }

    /**
     * Check if database configuration should be used
     *
     * @return bool
     */
    private function useDatabaseConfig(): bool
    {
        $configValue = config('hawki.ai_config_system');
        $useDatabaseConfig = $configValue === true || $configValue === 'database';
        
        // Log the mode for debugging (can be removed in production)
        if (config('app.debug')) {
            
        }
        
        return $useDatabaseConfig;
    }

    /**
     * Get default models from database
     *
     * @param ModelUsageType $usageType
     * @return array
     */
    private function getDefaultModelsFromDatabase(ModelUsageType $usageType): array
    {
        $cacheKey = self::CACHE_KEY_DEFAULT_MODELS . '_' . $usageType->value;
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($usageType) {
            $models = [];
            
            // Get default_model from ai_assistants table
            $defaultModelAssistant = AiAssistant::where('key', 'default_model')
                ->where('status', 'active')
                ->first();
            
            if ($defaultModelAssistant && $defaultModelAssistant->ai_model) {
                // Resolve system_id to model_id for AI system compatibility
                $modelId = $this->resolveSystemIdToModelId($defaultModelAssistant->ai_model);
                if ($modelId) {
                    $models['default_model'] = $modelId;
                }
            }

            // Get web_search model from ai_assistants table (if exists)
            $webSearchAssistant = AiAssistant::where('key', 'web_search')
                ->where('status', 'active')
                ->first();
            
            if ($webSearchAssistant && $webSearchAssistant->ai_model) {
                $modelId = $this->resolveSystemIdToModelId($webSearchAssistant->ai_model);
                if ($modelId) {
                    $models['default_web_search_model'] = $modelId;
                }
            }

            // Get file_upload model from ai_assistants table (if exists)
            $fileUploadAssistant = AiAssistant::where('key', 'file_upload')
                ->where('status', 'active')
                ->first();
            
            if ($fileUploadAssistant && $fileUploadAssistant->ai_model) {
                $modelId = $this->resolveSystemIdToModelId($fileUploadAssistant->ai_model);
                if ($modelId) {
                    $models['default_file_upload_model'] = $modelId;
                }
            }

            // Get vision model from ai_assistants table (if exists)
            $visionAssistant = AiAssistant::where('key', 'vision')
                ->where('status', 'active')
                ->first();
            
            if ($visionAssistant && $visionAssistant->ai_model) {
                $modelId = $this->resolveSystemIdToModelId($visionAssistant->ai_model);
                if ($modelId) {
                    $models['default_vision_model'] = $modelId;
                }
            }
            
            return $models;
        });
    }

    /**
     * Get system models from database
     *
     * @param ModelUsageType $usageType
     * @return array
     */
    private function getSystemModelsFromDatabase(ModelUsageType $usageType): array
    {
        $cacheKey = self::CACHE_KEY_SYSTEM_MODELS . '_' . $usageType->value;
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($usageType) {
            $models = [];
            
            // Map of system model keys in ai_assistants table
            $systemModelKeys = ['title_generator', 'prompt_improver', 'summarizer'];
            
            foreach ($systemModelKeys as $key) {
                $assistant = AiAssistant::where('key', $key)
                    ->where('status', 'active')
                    ->first();
                
                if ($assistant && $assistant->ai_model) {
                    // Resolve system_id to model_id for AI system compatibility
                    $modelId = $this->resolveSystemIdToModelId($assistant->ai_model);
                    if ($modelId) {
                        $models[$key] = $modelId;
                    }
                }
            }
            
            return $models;
        });
    }

    /**
     * Get default models from config files
     *
     * @param ModelUsageType $usageType
     * @return array
     */
    private function getDefaultModelsFromConfig(ModelUsageType $usageType): array
    {
        if ($usageType === ModelUsageType::EXTERNAL_APP) {
            $extAppModels = config('model_providers.default_models.ext_app', []);
            $defaultModels = config('model_providers.default_models', []);
            
            // Merge with default models, preferring ext_app values where they exist
            return array_merge($defaultModels, array_filter($extAppModels, fn($value) => $value !== null));
        }
        
        return config('model_providers.default_models', []);
    }

    /**
     * Get system models from config files
     *
     * @param ModelUsageType $usageType
     * @return array
     */
    private function getSystemModelsFromConfig(ModelUsageType $usageType): array
    {
        if ($usageType === ModelUsageType::EXTERNAL_APP) {
            $extAppModels = config('model_providers.system_models.ext_app', []);
            $systemModels = config('model_providers.system_models', []);
            
            // Merge with system models, preferring ext_app values where they exist
            return array_merge($systemModels, array_filter($extAppModels, fn($value) => $value !== null));
        }
        
        return config('model_providers.system_models', []);
    }

    /**
     * Get providers from config files
     *
     * @return array
     */
    private function getProvidersFromConfig(): array
    {
        $providers = config('model_providers.providers', []);
        
        // Add 'visible' property to all models from config files for consistency
        // Config-based models are visible by default
        foreach ($providers as $providerKey => &$provider) {
            if (isset($provider['models'])) {
                foreach ($provider['models'] as &$model) {
                    if (!isset($model['visible'])) {
                        $model['visible'] = true;
                    }
                }
            }
        }
        
        return $providers;
    }

    /**
     * Clear cached configuration
     *
     * @return void
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_DEFAULT_MODELS . '_' . ModelUsageType::DEFAULT->value);
        Cache::forget(self::CACHE_KEY_DEFAULT_MODELS . '_' . ModelUsageType::EXTERNAL_APP->value);
        Cache::forget(self::CACHE_KEY_SYSTEM_MODELS . '_' . ModelUsageType::DEFAULT->value);
        Cache::forget(self::CACHE_KEY_SYSTEM_MODELS . '_' . ModelUsageType::EXTERNAL_APP->value);
        Cache::forget(self::CACHE_KEY_PROVIDERS);
    }

    /**
     * Get providers from database
     *
     * @return array
     */
    private function getProvidersFromDatabase(): array
    {
        $cacheKey = self::CACHE_KEY_PROVIDERS;
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $providers = [];
            
            // Get active API providers from database
            $apiProviders = ApiProvider::where('is_active', true)->with('apiFormat')->get();
            
            foreach ($apiProviders as $apiProvider) {
                // Get models for this provider (all active models, visible and non-visible)
                // Non-visible models can still be used by AI Assistants, only UI filtering happens later
                $models = AiModel::where('provider_id', $apiProvider->id)
                    ->where('is_active', true)
                    ->orderBy('display_order')
                    ->get();
                
                $modelConfigs = [];
                foreach ($models as $model) {
                    // Build tools array from settings field, with defaults
                    $tools = $this->buildToolsFromSettings($model);
                    
                    $modelConfigs[] = [
                        'id' => $model->model_id,
                        'label' => $model->label,
                        'active' => $model->is_active,
                        'visible' => $model->is_visible,
                        'input' => ['text'], // Default capabilities
                        'output' => ['text'],
                        'tools' => $tools,
                        'system_id' => $model->system_id, // Keep system_id for reference
                        'status' => 'online', // Always set to online for UI (real status check implemented later)
                        'display_order' => $model->display_order,
                        'provider_name' => $apiProvider->provider_name,
                        'provider_display_order' => $apiProvider->display_order
                    ];
                }
                
                // Build endpoint URLs from api_format_endpoints
                $endpoints = $this->buildEndpointsForProvider($apiProvider);
                
                // Adjust URLs to match file-based config format for compatibility with existing adapters
                $apiUrl = $this->buildCompatibleApiUrl($apiProvider, $endpoints);
                $streamUrl = $this->buildCompatibleStreamUrl($apiProvider, $endpoints);
                $pingUrl = $endpoints['models.list'] ?? $apiProvider->base_url;
                
                // Get adapter name from API format's client_adapter field
                $adapter = $this->getAdapterFromApiFormat($apiProvider);
                
                $providers[$apiProvider->provider_name] = [
                    'active' => $apiProvider->is_active,
                    'adapter' => $adapter, // Map to existing client classes
                    'api_key' => $apiProvider->api_key,
                    'api_url' => $apiUrl,
                    'stream_url' => $streamUrl,
                    'ping_url' => $pingUrl,
                    'models' => $modelConfigs
                ];
            }
            
            return $providers;
        });
    }

    /**
     * Build endpoint URLs for a provider based on api_format_endpoints
     *
     * @param ApiProvider $apiProvider
     * @return array
     */
    private function buildEndpointsForProvider(ApiProvider $apiProvider): array
    {
        $endpoints = [];
        
        // Get all endpoints for this provider's API format
        $formatEndpoints = ApiFormatEndpoint::where('api_format_id', $apiProvider->api_format_id)
            ->where('is_active', true)
            ->get();
            
        foreach ($formatEndpoints as $endpoint) {
            $fullUrl = rtrim($apiProvider->base_url, '/') . $endpoint->path;
            $endpoints[$endpoint->name] = $fullUrl;
        }
        
        return $endpoints;
    }

    /**
     * Get resolved model_id from system_id (for future use)
     *
     * @param string $systemId
     * @return string|null
     */
    public function resolveSystemIdToModelId(string $systemId): ?string
    {
        $aiModel = AiModel::where('system_id', $systemId)->first();
        return $aiModel?->model_id;
    }

    /**
     * Build compatible API URL for specific providers to match file-based config format
     *
     * @param ApiProvider $apiProvider
     * @param array $endpoints
     * @return string
     */
    private function buildCompatibleApiUrl(ApiProvider $apiProvider, array $endpoints): string
    {
        $providerName = strtolower($apiProvider->provider_name);
        $adapter = strtolower($apiProvider->apiFormat->client_adapter ?? '');
        
        // For Google provider, we need to match the file-based config format
        if ($providerName === 'google' || $adapter === 'google') {
            // File-based format: https://generativelanguage.googleapis.com/v1beta/models/
            // Database endpoint: /models/{model}:generateContent
            // We need to return the base URL + /models/ to match file-based behavior
            return rtrim($apiProvider->base_url, '/') . '/models/';
        }

        // For Responses API, use direct endpoint (check adapter, not provider name)
        if ($adapter === 'responses') {
            return $endpoints['responses.create'] ?? $apiProvider->base_url;
        }

        // For other providers, use the endpoint as-is
        return $endpoints['chat.create'] ?? $apiProvider->base_url;
    }

    /**
     * Build compatible stream URL for specific providers to match file-based config format
     *
     * @param ApiProvider $apiProvider
     * @param array $endpoints
     * @return string
     */
    private function buildCompatibleStreamUrl(ApiProvider $apiProvider, array $endpoints): string
    {
        $providerName = strtolower($apiProvider->provider_name);
        $adapter = strtolower($apiProvider->apiFormat->client_adapter ?? '');
        
        // For Google provider, use the same format as API URL
        if ($providerName === 'google' || $adapter === 'google') {
            return rtrim($apiProvider->base_url, '/') . '/models/';
        }

        // For Responses API, streaming uses the same endpoint (check adapter, not provider name)
        if ($adapter === 'responses') {
            return $endpoints['responses.create'] ?? $apiProvider->base_url;
        }

        // For other providers, use the stream endpoint or fall back to chat.create
        return $endpoints['chat.stream'] ?? $endpoints['chat.create'] ?? $apiProvider->base_url;
    }

    /**
     * Get adapter name from API format's client_adapter field
     *
     * @param ApiProvider $apiProvider
     * @return string
     */
    private function getAdapterFromApiFormat(ApiProvider $apiProvider): string
    {
        // Use client_adapter from api_formats table if available
        $adapter = $apiProvider->apiFormat?->client_adapter;
        
        if (empty($adapter)) {
            // Fallback: use the provider name as adapter (lowercase)
            $adapter = strtolower($apiProvider->provider_name);
        }
        
        // Normalize adapter name to match actual directory/class names
        // This handles case-sensitivity issues between macOS (case-insensitive) and Linux (case-sensitive)
        return $this->normalizeAdapterName($adapter);
    }
    
    /**
     * Normalize adapter names to match actual directory/class names.
     * This handles case-sensitivity issues between macOS (case-insensitive) and Linux (case-sensitive).
     *
     * @param string $adapterName
     * @return string
     */
    private function normalizeAdapterName(string $adapterName): string
    {
        // Map of lowercase adapter names to their correct PascalCase directory names
        $adapterMap = [
            'openai' => 'OpenAi',
            'responses' => 'Responses',
            'google' => 'Google',
            'gwdg' => 'Gwdg',
            'ollama' => 'Ollama',
            'openwebui' => 'OpenWebUI',
            'anthropic' => 'Anthropic',
        ];
        
        $lowerName = strtolower($adapterName);
        return $adapterMap[$lowerName] ?? ucfirst($adapterName);
    }
    
    /**
     * Build tools array from model settings field with sensible defaults
     *
     * @param AiModel $model
     * @return array
     */
    private function buildToolsFromSettings(AiModel $model): array
    {
        // Default tools configuration
        // Note: stream is always true (deprecated field, kept for compatibility)
        $defaultTools = [
            'stream' => true,
            'file_upload' => false,
            'vision' => false,
            'web_search' => false,
        ];
        
        // Get tools from settings field if available
        $settings = $model->settings ?? [];
        $settingsTools = $settings['tools'] ?? [];
        
        // Merge settings with defaults (settings override defaults)
        $tools = array_merge($defaultTools, $settingsTools);
        
        // Ensure all values are boolean
        foreach ($tools as $key => $value) {
            $tools[$key] = (bool) $value;
        }
        
        // Stream is always true (deprecated but kept for compatibility)
        $tools['stream'] = true;
        
        return $tools;
    }
}