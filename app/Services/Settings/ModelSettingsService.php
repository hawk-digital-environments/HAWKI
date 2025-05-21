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
        Log::debug("=== Starting getModelStatus for provider: {$providerName} ===");

        $provider = ProviderSetting::where('provider_name', $providerName)->first();

        if (!$provider) {
            Log::error("Provider '{$providerName}' not found in database");
            throw new \Exception("Provider '{$providerName}' not found");
        }

        Log::debug("Provider details: ID={$provider->id}, active={$provider->is_active}, ping_url=" . ($provider->ping_url ?? 'null'));

        if (!$provider->is_active) {
            Log::error("Provider '{$providerName}' is not active");
            throw new \Exception("Provider '{$providerName}' is not active");
        }

        if (!$provider->ping_url) {
            Log::error("No ping URL configured for provider '{$providerName}'");
            throw new \Exception("No ping URL configured for provider '{$providerName}'");
        }

        Log::debug("Fetching models from provider: {$providerName} at URL: {$provider->ping_url}");

        try {
            $models = $this->fetchModelsFromProvider($provider);

            Log::debug("Models fetched successfully from {$providerName}: " . json_encode([
                'count' => count($models),
                'model_ids' => array_keys($models)
            ]));

            return $models;
        } catch (\Exception $e) {
            Log::error("Error in getModelStatus for {$providerName}: " . $e->getMessage());
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

        Log::debug("== fetchModelsFromProvider ==");
        Log::debug("provider={$provider->provider_name}, apiFormat={$apiFormat}, pingUrl={$pingUrl}");

        try {
            $result = [];

            // Different provider types may have different API formats
            switch ($apiFormat) {
                case 'openai':
                    Log::debug("Using openAI fetcher for {$provider->provider_name}");
                    $result = $this->fetchOpenAIModels($pingUrl, $apiKey);
                    break;

                case 'openWebUi':
                case 'oobabooga':
                    Log::debug("Using openWebUi fetcher for {$provider->provider_name}");
                    $result = $this->fetchOpenWebUiModels($pingUrl, $apiKey);
                    break;
                
                case 'ollama':
                    Log::debug("Using Ollama fetcher for {$provider->provider_name}");
                    $result = $this->fetchOllamaModels($pingUrl, $apiKey);
                    break;
                
                case 'gwdg':
                    Log::debug("Using GWDG fetcher for {$provider->provider_name}");
                    $result = $this->fetchGWDGModels($pingUrl, $apiKey);
                    break;

                case 'google':
                    Log::debug("Using Google fetcher for {$provider->provider_name}");
                    $result = $this->fetchGoogleModels($pingUrl, $apiKey);
                    break;

                default:
                    Log::debug("Using generic fetcher for {$provider->provider_name}");
                    $result = $this->fetchGenericModels($pingUrl, $apiKey);
                    break;
            }

            Log::debug("Provider {$provider->provider_name} - Models retrieved: " . count($result));
            if (count($result) > 0) {
                $sample = array_slice($result, 0, 2);
                Log::debug("Sample models from {$provider->provider_name}: " . json_encode($sample));
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("Error fetching models from {$provider->provider_name}: " . $e->getMessage());
            throw new \Exception("Failed to fetch models: " . $e->getMessage());
        }
    }
    
    /**
     * Fetch OpenAI models.
     *
     * @param string $pingUrl
     * @param string $apiKey
     * @return array
     * @throws \Exception
     */
    private function fetchOpenAIModels(string $pingUrl, string $apiKey): array
    {
        // Mask for log entries
        Log::debug("Fetching OpenAI models from {$pingUrl} with key: " . substr($apiKey, 0, 5) . "...");
        
        try {
            // Send with complete API key
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
            ])->get($pingUrl);
            
            if (!$response->successful()) {
                $statusCode = $response->status();
                $responseBody = $response->body();
                Log::error("OpenAI API request failed: Status={$statusCode}, Body: " . substr($responseBody, 0, 200));
                throw new \Exception("Failed to fetch OpenAI models: HTTP {$statusCode}");
            }
            
            // Return the complete API response without filtering
            $data = $response->json();
            Log::debug("OpenAI raw response structure: " . json_encode(array_keys($data)));
            
            return $data;
        } catch (\Exception $e) {
            Log::error("Exception in fetchOpenAIModels: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Fetch OpenWebUi/Oobabooga models.
     *
     * @param string $pingUrl
     * @param string $apiKey
     * @return array
     * @throws \Exception
     */
    private function fetchOpenWebUiModels(string $pingUrl, string $apiKey): array
    {
        // Mask for log entries
        Log::debug("Fetching OpenWebUi models from {$pingUrl} with key: " . substr($apiKey, 0, 5) . "...");
        
        try {
            $headers = [];
            if ($apiKey) {
            // Send with complete API key
            $headers['Authorization'] = "Bearer {$apiKey}";
            }
            
            $response = Http::withHeaders($headers)->get($pingUrl);
            
            if (!$response->successful()) {
            $statusCode = $response->status();
            $responseBody = $response->body();
            Log::error("OpenWebUi API request failed: Status={$statusCode}, Body: " . substr($responseBody, 0, 200));
            throw new \Exception("Failed to fetch OpenWebUi models: HTTP {$statusCode}");
            }
            
            // Return the complete API response without filtering
            $data = $response->json();
            Log::debug("OpenWebUi raw response structure: " . json_encode(array_keys($data)));
            
            return $data;
        } catch (\Exception $e) {
            Log::error("Exception in fetchOpenWebUiModels: " . $e->getMessage());
            throw $e;
        }
    }

   /**
 * Fetch Ollama models.
 *
 * @param string $pingUrl
 * @param string $apiKey
 * @return array
 * @throws \Exception
 */
private function fetchOllamaModels(string $pingUrl, string $apiKey): array
{
    Log::debug("Fetching Ollama models from {$pingUrl}");
    
    try {
        $headers = [];
        if ($apiKey) {
            $headers['Authorization'] = "Bearer {$apiKey}";
        }
        
        $response = Http::withHeaders($headers)->get($pingUrl);
        
        if (!$response->successful()) {
            $statusCode = $response->status();
            $responseBody = $response->body();
            Log::error("Ollama API request failed: Status={$statusCode}, Body: " . substr($responseBody, 0, 200));
            throw new \Exception("Failed to fetch Ollama models: HTTP {$statusCode}");
        }
        
        // Retrieve raw API result
        $rawData = $response->json();
        Log::debug("Ollama raw response: " . json_encode($rawData));
        
        // Structure models
        $result = [];
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
                }
            }
        } else {
            Log::warning("Unexpected Ollama API response format - 'models' array missing");
        }
        
        Log::debug("Processed " . count($result) . " Ollama models");
        return $result;
    } catch (\Exception $e) {
        Log::error("Exception in fetchOllamaModels: " . $e->getMessage());
        throw $e;
    }
}

    /**
     * Fetch GWDG models.
     *
     * @param string $pingUrl
     * @param string $apiKey
     * @return array
     * @throws \Exception
     */
    private function fetchGWDGModels(string $pingUrl, string $apiKey): array
    {
        // Mask for log entries
        Log::debug("Fetching GWDG models from {$pingUrl} with key: " . substr($apiKey, 0, 5) . "...");
        
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
            ])->get($pingUrl);
            
            if (!$response->successful()) {
                $statusCode = $response->status();
                Log::error("GWDG API request failed: Status={$statusCode}");
                throw new \Exception("Failed to fetch GWDG models: HTTP {$statusCode}");
            }
            
            // Return the complete API response without filtering
            $data = $response->json();
            Log::debug("GWDG raw response received with " . count($data) . " items");
            
            return $data;
        } catch (\Exception $e) {
            Log::error("Exception in fetchGWDGModels: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Fetch Google models.
     *
     * @param string $pingUrl
     * @param string $apiKey
     * @return array
     * @throws \Exception
     */
    private function fetchGoogleModels(string $pingUrl, string $apiKey): array
    {
        // Mask for log entries
        Log::debug("Fetching Google models from {$pingUrl} with key: " . substr($apiKey, 0, 5) . "...");
        
        try {
            // Send with complete API key
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
            ])->get($pingUrl);
            
            if (!$response->successful()) {
                $statusCode = $response->status();
                $responseBody = $response->body();
                Log::error("Google API request failed: Status={$statusCode}, Body: " . substr($responseBody, 0, 200));
                throw new \Exception("Failed to fetch Google models: HTTP {$statusCode}");
            }
            
            // Return the complete API response without filtering
            $data = $response->json();
            Log::debug("Google raw response structure: " . json_encode(array_keys($data)));
            
            return $data;
        } catch (\Exception $e) {
            Log::error("Exception in fetchGoogleModels: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Fetch models from a generic provider.
     *
     * @param string $pingUrl
     * @param string $apiKey
     * @return array
     * @throws \Exception
     */
    private function fetchGenericModels(string $pingUrl, string $apiKey): array
    {
        // Mask for log entries
        Log::debug("Fetching generic models from {$pingUrl}");
        
        try {
            $headers = [];
            if ($apiKey) {
                $headers['Authorization'] = "Bearer {$apiKey}";
            }
            
            $response = Http::withHeaders($headers)->get($pingUrl);
            
            if (!$response->successful()) {
                $statusCode = $response->status();
                Log::error("Generic API request failed: Status={$statusCode}");
                throw new \Exception("Failed to fetch models: HTTP {$statusCode}");
            }
            
            // Return the complete API response without filtering
            $data = $response->json();
            Log::debug("Generic raw response received with data structure: " . json_encode(array_keys($data)));
            
            return $data;
        } catch (\Exception $e) {
            Log::error("Exception in fetchGenericModels: " . $e->getMessage());
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
