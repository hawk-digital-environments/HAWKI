<?php

namespace App\Services\Settings;

use App\Models\ProviderSetting;
use App\Models\LanguageModel;

use App\Services\ProviderSettingsService;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use Orchid\Support\Facades\Toast;

class ModelSettingsService
{

    /**
     * @var ProviderSettingsService
     */
     protected $providerSettingsService;

    /**
     * Constructor.
     *
     * @param ProviderSettingsService $providerSettingsService
     */
    public function __construct(ProviderSettingsService $providerSettingsService)
    {
        $this->providerSettingsService = $providerSettingsService;
    }

    /**
     * Get the status of all available models for a specific provider.
     *
     * @param string $providerName
     * @return array
     * @throws \Exception
     */
    public function getModelStatus(string $providerName): array
    {
        $provider = ProviderSetting::where('provider_name', $providerName)->first();

        if (!$provider) {
            Log::error("Provider '{$providerName}' not found in database");
            throw new \Exception("Provider '{$providerName}' not found");
        }

        if (!$provider->is_active) {
            Log::error("Provider '{$providerName}' is not active");
            throw new \Exception("Provider '{$providerName}' is not active");
        }

        if (!$provider->ping_url) {
            Log::error("No ping URL configured for provider '{$providerName}'");
            throw new \Exception("No ping URL configured for provider '{$providerName}'");
        }

        try {
            // The detailed logging is handled in the provider-specific fetch methods
            return $this->fetchModelsFromProvider($provider);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve models from provider", [
                'provider_name' => $providerName,
                'provider_id' => $provider->id,
                'api_format' => $provider->api_format,
                'ping_url' => $provider->ping_url,
                'error' => $e->getMessage(),
                'status' => 'error'
            ]);
            throw $e;
        }
    }

    /**
     * Fetch models from a provider's API.
     *
     * @param ProviderSetting $provider
     * @return array
     * @throws \Exception
     */
    private function fetchModelsFromProvider(ProviderSetting $provider): array
    {
        $apiFormat = $provider->api_format ?? $provider->provider_name;
        $pingUrl = $provider->ping_url;
        $apiKey = $provider->api_key;

        try {
            $result = [];

            // Different provider types may have different API formats
            switch ($apiFormat) {
                case 'openai':
                    $result = $this->fetchOpenAIModels($pingUrl, $apiKey);
                    break;

                case 'openWebUi':
                case 'oobabooga':
                    $result = $this->fetchOpenWebUiModels($pingUrl, $apiKey);
                    break;
                
                case 'ollama':
                    $result = $this->fetchOllamaModels($pingUrl, $apiKey);
                    break;
                
                case 'gwdg':
                    $result = $this->fetchGWDGModels($pingUrl, $apiKey);
                    break;

                case 'google':
                    $result = $this->fetchGoogleModels($pingUrl, $apiKey);
                    break;

                default:
                    $result = $this->fetchGenericModels($pingUrl, $apiKey);
                    break;
            }

            return $result;
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch models: " . $e->getMessage());
        }
    }
    
    /**
     * Fetch OpenAI models.
     *
     * @param string $pingUrl
     * @param string|null $apiKey
     * @return array
     * @throws \Exception
     */
    private function fetchOpenAIModels(string $pingUrl, ?string $apiKey): array
    {
        $startTime = microtime(true);
        $keyMask = $apiKey ? substr($apiKey, 0, 5) . "..." : "none";
        
        try {
            $headers = [];
            if ($apiKey) {
                $headers['Authorization'] = "Bearer {$apiKey}";
            }
            
            $response = Http::withHeaders($headers)->get($pingUrl);
            
            if (!$response->successful()) {
                $logData = [
                    'provider' => 'openai',
                    'url' => $pingUrl,
                    'api_key' => $keyMask,
                    'status' => 'error',
                    'http_status' => $response->status(),
                    'error' => 'HTTP request failed',
                    'response_body' => substr($response->body(), 0, 200),
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ];
                Log::error("OpenAI model fetch failed", $logData);
                throw new \Exception("Failed to fetch OpenAI models: HTTP {$response->status()}");
            }
            
            $data = $response->json();
            
            return $data;
        } catch (\Exception $e) {
            $logData = [
                'provider' => 'openai',
                'url' => $pingUrl,
                'api_key' => $keyMask,
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ];
            Log::error("OpenAI model fetch exception: " . json_encode($logData));
            throw $e;
        }
    }
    
    /**
     * Fetch OpenWebUi/Oobabooga models.
     *
     * @param string $pingUrl
     * @param string|null $apiKey
     * @return array
     * @throws \Exception
     */
    private function fetchOpenWebUiModels(string $pingUrl, ?string $apiKey): array
    {
        $startTime = microtime(true);
        $keyMask = $apiKey ? substr($apiKey, 0, 5) . "..." : "none";
        
        try {
            $headers = [];
            if ($apiKey) {
                $headers['Authorization'] = "Bearer {$apiKey}";
            }
            
            $response = Http::withHeaders($headers)->get($pingUrl);
            
            if (!$response->successful()) {
                $logData = [
                    'provider' => 'openwebui',
                    'url' => $pingUrl,
                    'api_key' => $keyMask,
                    'status' => 'error',
                    'http_status' => $response->status(),
                    'error' => 'HTTP request failed',
                    'response_body' => substr($response->body(), 0, 200),
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ];
                Log::error("OpenWebUi model fetch failed", $logData);
                throw new \Exception("Failed to fetch OpenWebUi models: HTTP {$response->status()}");
            }
            
            $data = $response->json();
            
            return $data;
        } catch (\Exception $e) {
            $logData = [
                'provider' => 'openwebui',
                'url' => $pingUrl,
                'api_key' => $keyMask,
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ];
            Log::error("OpenWebUi model fetch exception: " . json_encode($logData));
            throw $e;
        }
    }

   /**
 * Fetch Ollama models.
 *
 * @param string $pingUrl
 * @param string|null $apiKey
 * @return array
 * @throws \Exception
 */
private function fetchOllamaModels(string $pingUrl, ?string $apiKey): array
{
    $startTime = microtime(true);
    $keyMask = $apiKey ? substr($apiKey, 0, 5) . "..." : "none";
    $logData = [
        'provider' => 'ollama',
        'url' => $pingUrl,
        'api_key' => $keyMask,
        'timestamp' => now()->toISOString()
    ];
    
    try {
        $headers = [];
        if ($apiKey) {
            $headers['Authorization'] = "Bearer {$apiKey}";
        }
        
        $response = Http::withHeaders($headers)->get($pingUrl);
        
        if (!$response->successful()) {
            $logData['status'] = 'error';
            $logData['http_status'] = $response->status();
            $logData['error'] = 'HTTP request failed';
            $logData['response_body'] = substr($response->body(), 0, 200);
            $logData['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::error("Ollama model fetch failed", $logData);
            throw new \Exception("Failed to fetch Ollama models: HTTP {$response->status()}");
        }
        
        // Retrieve raw API result
        $rawData = $response->json();
        
        // Structure models
        $result = [];
        $modelDetails = [];
        
        if (isset($rawData['models']) && is_array($rawData['models'])) {
            foreach ($rawData['models'] as $model) {
                $modelId = $model['name'] ?? $model['model'] ?? null;
                if ($modelId) {
                    // Format model information into a standardized format
                    $paramSize = isset($model['details']['parameter_size']) ? 
                        " (" . $model['details']['parameter_size'] . ")" : "";
                    $family = isset($model['details']['family']) ? $model['details']['family'] : "";
                    
                    $result[$modelId] = [
                        'id' => $modelId,
                        'name' => $modelId,
                        'displayName' => ($family ? $family . ' ' : '') . $modelId . $paramSize,
                        'description' => isset($model['details']) ? json_encode($model['details']) : '',
                        'size' => $model['size'] ?? 0,
                        'modified_at' => $model['modified_at'] ?? '',
                        'family' => $family,
                        'quantization' => $model['details']['quantization_level'] ?? '',
                    ];
                    
                    // Collect model details for logging
                    $modelDetails[] = [
                        'id' => $modelId,
                        'family' => $family,
                        'size' => $model['size'] ?? 0,
                        'parameter_size' => $model['details']['parameter_size'] ?? ''
                    ];
                }
            }
        }
        
        return $result;
    } catch (\Exception $e) {
        $logData['status'] = 'error';
        $logData['error'] = $e->getMessage();
        $logData['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
        
        Log::error("Ollama model fetch exception: " . json_encode($logData));
        throw $e;
    }
}

    /**
     * Fetch GWDG models.
     *
     * @param string $pingUrl
     * @param string|null $apiKey
     * @return array
     * @throws \Exception
     */
    private function fetchGWDGModels(string $pingUrl, ?string $apiKey): array
    {
        $startTime = microtime(true);
        $keyMask = $apiKey ? substr($apiKey, 0, 5) . "..." : "none";
        
        try {
            $headers = [];
            if ($apiKey) {
                $headers['Authorization'] = "Bearer {$apiKey}";
            }
            
            $response = Http::withHeaders($headers)->get($pingUrl);
            
            if (!$response->successful()) {
                $logData = [
                    'provider' => 'gwdg',
                    'url' => $pingUrl,
                    'api_key' => $keyMask,
                    'status' => 'error',
                    'http_status' => $response->status(),
                    'error' => 'HTTP request failed',
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ];
                Log::error("GWDG model fetch failed", $logData);
                throw new \Exception("Failed to fetch GWDG models: HTTP {$response->status()}");
            }
            
            $data = $response->json();
            
            return $data;
        } catch (\Exception $e) {
            $logData = [
                'provider' => 'gwdg',
                'url' => $pingUrl,
                'api_key' => $keyMask,
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ];
            Log::error("GWDG model fetch exception: " . json_encode($logData));
            throw $e;
        }
    }
    
    /**
     * Fetch Google models.
     *
     * @param string $pingUrl
     * @param string|null $apiKey
     * @return array
     * @throws \Exception
     */
    private function fetchGoogleModels(string $pingUrl, ?string $apiKey): array
    {
        $startTime = microtime(true);
        $keyMask = $apiKey ? substr($apiKey, 0, 5) . "..." : "none";
        
        try {
            $headers = [];
            if ($apiKey) {
                $headers['Authorization'] = "Bearer {$apiKey}";
            }
            
            $response = Http::withHeaders($headers)->get($pingUrl);
            
            if (!$response->successful()) {
                $logData = [
                    'provider' => 'google',
                    'url' => $pingUrl,
                    'api_key' => $keyMask,
                    'status' => 'error',
                    'http_status' => $response->status(),
                    'error' => 'HTTP request failed',
                    'response_body' => substr($response->body(), 0, 200),
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ];
                Log::error("Google model fetch failed", $logData);
                throw new \Exception("Failed to fetch Google models: HTTP {$response->status()}");
            }
            
            $data = $response->json();
            
            return $data;
        } catch (\Exception $e) {
            $logData = [
                'provider' => 'google',
                'url' => $pingUrl,
                'api_key' => $keyMask,
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ];
            Log::error("Google model fetch exception: " . json_encode($logData));
            throw $e;
        }
    }
    
    /**
     * Fetch models from a generic provider.
     *
     * @param string $pingUrl
     * @param string|null $apiKey
     * @return array
     * @throws \Exception
     */
    private function fetchGenericModels(string $pingUrl, ?string $apiKey): array
    {
        $startTime = microtime(true);
        $keyMask = $apiKey ? substr($apiKey, 0, 5) . "..." : "none";
        
        try {
            $headers = [];
            if ($apiKey) {
                $headers['Authorization'] = "Bearer {$apiKey}";
            }
            
            $response = Http::withHeaders($headers)->get($pingUrl);
            
            if (!$response->successful()) {
                $logData = [
                    'provider' => 'generic',
                    'url' => $pingUrl,
                    'api_key' => $keyMask,
                    'status' => 'error',
                    'http_status' => $response->status(),
                    'error' => 'HTTP request failed',
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ];
                Log::error("Generic model fetch failed", $logData);
                throw new \Exception("Failed to fetch models: HTTP {$response->status()}");
            }
            
            $data = $response->json();
            
            return $data;
        } catch (\Exception $e) {
            $logData = [
                'provider' => 'generic',
                'url' => $pingUrl,
                'api_key' => $keyMask,
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ];
            Log::error("Generic model fetch exception: " . json_encode($logData));
            throw $e;
        }
    }
    /**
     * Deletes a language model from the database
     *
     * @param int $id The ID of the model to delete
     * @return bool
     */
    public function deleteModel(int $id): bool
    {
        try {
            $model = LanguageModel::find($id);
            
            if (!$model) {
                Log::warning("Model with ID {$id} not found for deletion");
                return false;
            }
            
            $modelName = $model->label;
            
            // Delete the model
            $result = $model->delete();
            
            if ($result) {
                Log::info("Model '{$modelName}' (ID: {$id}) was successfully deleted");
                return true;
            } else {
                Log::error("Failed to delete model '{$modelName}' (ID: {$id})");
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Error deleting model with ID {$id}: " . $e->getMessage());
            return false;
        }
    }
}
