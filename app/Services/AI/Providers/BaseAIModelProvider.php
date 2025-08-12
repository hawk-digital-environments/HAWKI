<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Interfaces\AIModelProviderInterface;
use App\Models\LanguageModel;
use App\Models\ProviderSetting;
use Illuminate\Support\Facades\Log;

abstract class BaseAIModelProvider implements AIModelProviderInterface
{
    /**
     * Provider configuration
     * 
     * @var array
     */
    protected $config;
    
    /**
     * Provider identifier
     * 
     * @var string
     */
    protected $providerId;
    
    /**
     * Create a new provider instance
     * 
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        
        // Try to extract the provider identifier from the configuration
        // Use api_format if available, otherwise fall back to provider_name
        if (!isset($this->providerId)) {
            if (isset($config['api_format'])) {
                $this->providerId = $config['api_format'];
            } elseif (isset($config['provider_name'])) {
                $this->providerId = $config['provider_name'];
            }
        }
    }
    
    /**
     * Extract usage information from the response data
     * 
     * @param array $data Response data
     * @return array|null Usage data or null if not available
     */
    protected function extractUsage(array $data): ?array
    {
        return null;
    }
    
    /**
     * Get details for a specific model from the database
     * 
     * @param string $modelId
     * @return array
     */
    public function getModelDetails(string $modelId): array
    {
        try {
            // Read model data from the database instead of from the configuration
            $model = LanguageModel::select('language_models.*', 'provider_settings.provider_name', 'provider_settings.api_format')
                ->join('provider_settings', 'language_models.provider_id', '=', 'provider_settings.id')
                ->where('language_models.model_id', $modelId)
                ->where('language_models.is_active', true)
                ->first();
            
            if (!$model) {
                Log::warning("Model not found in database: $modelId");
                return [
                    'id' => $modelId,
                    'label' => $modelId, // Fallback: Use ID as label
                    'streamable' => false,
                    'api_format' => $this->getProviderId(),
                    'provider_name' => $this->getProviderId()
                ];
            }
            
            $details = [
                'id' => $model->model_id,
                'label' => $model->label,
                'streamable' => $model->streamable,
                'api_format' => $model->api_format ?? $model->provider_name,
                'provider_name' => $model->provider_name
            ];
            
            // Add additional model information if available
            if (!empty($model->information)) {
                $information = is_array($model->information) ? 
                              $model->information : 
                              json_decode($model->information, true);
                
                if (is_array($information)) {
                    $details = array_merge($details, $information);
                }
            }
            
            // Add settings if available
            if (!empty($model->settings)) {
                $settings = is_array($model->settings) ? 
                          $model->settings : 
                          json_decode($model->settings, true);
                
                if (is_array($settings)) {
                    $details['settings'] = $settings;
                }
            }
            
            return $details;
        } catch (\Exception $e) {
            Log::error("Error getting model details: " . $e->getMessage());
            // Simple fallback response
            return [
                'id' => $modelId,
                'label' => $modelId,
                'streamable' => false,
                'api_format' => $this->getProviderId(),
                'provider_name' => $this->getProviderId()
            ];
        }
    }
    
    /**
     * Get all available models for this provider from the database
     * 
     * @return array
     */
    public function getAvailableModels(): array
    {
        try {
            $providerId = $this->getProviderId();
            
            // Get provider ID from the database by either api_format or provider_name
            $provider = ProviderSetting::where(function($query) use ($providerId) {
                $query->where('api_format', $providerId)
                      ->orWhere('provider_name', $providerId);
            })
            ->where('is_active', true)
            ->first();
                
            if (!$provider) {
                Log::warning("Provider not found in database: $providerId");
                return [];
            }
            
            // Retrieve all active models for this provider
            $models = LanguageModel::where('provider_id', $provider->id)
                ->where('is_active', true)
                ->orderBy('display_order')
                ->get();
                
            $modelsList = [];
            
            foreach ($models as $model) {
                $modelData = [
                    'id' => $model->model_id,
                    'label' => $model->label,
                    'streamable' => $model->streamable,
                    'api_format' => $provider->api_format ?? $provider->provider_name,
                    'provider_name' => $provider->provider_name
                ];
                
                // Extract status from the information field if available
                if (!empty($model->information)) {
                    try {
                        $information = is_array($model->information) ? 
                                      $model->information : 
                                      json_decode($model->information, true);
                        
                        if (is_array($information) && isset($information['status'])) {
                            $modelData['status'] = $information['status'];
                        }
                    } catch (\Exception $e) {
                        // Ignore and continue without status
                    }
                }
                
                $modelsList[] = $modelData;
            }
            
            return $modelsList;
        } catch (\Exception $e) {
            Log::error("Error getting available models: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get the provider ID
     * 
     * @return string
     */
    public function getProviderId(): string
    {
        return $this->providerId ?? 'unknown_provider';
    }
    
    /**
     * Check if a model supports streaming
     * 
     * @param string $modelId Model identifier
     * @return bool True if streaming is supported
     */
    public function supportsStreaming(string $modelId): bool
    {
        return $this->getModelDetails($modelId)['streamable'] ?? false;
    }
    
    /**
     * Establish a connection to the AI provider's API
     *
     * @param array $payload The formatted payload
     * @param callable|null $streamCallback Callback for streaming responses
     * @return mixed The response or void for streaming
     */
    public function connect(array $payload, ?callable $streamCallback = null)
    {
        $modelId = $payload['model'];
        
        // Determine whether to use streaming or non-streaming
        if ($streamCallback && $this->supportsStreaming($modelId)) {
            return $this->makeStreamingRequest($payload, $streamCallback);
        } else {
            return $this->makeNonStreamingRequest($payload);
        }
    }
    
    /**
     * Set up common HTTP headers for API requests
     *
     * @param bool $isStreaming Whether this is a streaming request
     * @return array
     */
    protected function getHttpHeaders(bool $isStreaming = false): array
    {
        $headers = [
            'Content-Type: application/json'
        ];
        
        // Add authorization header if API key is present
        if (!empty($this->config['api_key'])) {
            $headers[] = 'Authorization: Bearer ' . $this->config['api_key'];
        }
        
        return $headers;
    }
    
    /**
     * Set common cURL options for all requests
     *
     * @param resource $ch cURL resource
     * @param array $payload Request payload
     * @param array $headers HTTP headers
     * @return void
     */
    protected function setCommonCurlOptions($ch, array $payload, array $headers): void
    {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    }
    
    /**
     * Set up streaming-specific cURL options
     *
     * @param resource $ch cURL resource
     * @param callable $streamCallback Callback for processing chunks
     * @return void
     */
    protected function setStreamingCurlOptions($ch, callable $streamCallback): void
    {
        // Set timeout parameters for streaming
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1);
        curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 20);
        
        // Process each chunk as it arrives
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($streamCallback) {
            if (connection_aborted()) {
                return 0;
            }
            
            $streamCallback($data);
            
            if (config('logging.triggers.return_object')) {
                Log::info($data);
            }
            if (ob_get_length()) {
                ob_flush();
            }
            flush();
            
            return strlen($data);
        });
    }
}