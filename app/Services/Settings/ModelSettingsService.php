<?php

namespace App\Services\Settings;

use App\Models\LanguageModel;
use App\Models\ProviderSetting;
use App\Services\AI\AIProviderFactory;
use App\Services\ProviderSettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ModelSettingsService
{
    /**
     * @var ProviderSettingsService
     */
    protected $providerSettingsService;

    /**
     * @var AIProviderFactory
     */
    protected $aiProviderFactory;

    /**
     * Constructor.
     */
    public function __construct(ProviderSettingsService $providerSettingsService, AIProviderFactory $aiProviderFactory)
    {
        $this->providerSettingsService = $providerSettingsService;
        $this->aiProviderFactory = $aiProviderFactory;
    }

    /**
     * Get the status of all available models for a specific provider.
     *
     * @throws \Exception
     */
    public function getModelStatus(string $providerName): array
    {
        $provider = ProviderSetting::with('apiFormat.endpoints')->where('provider_name', $providerName)->first();

        if (! $provider) {
            Log::error("Provider '{$providerName}' not found in database");
            throw new \Exception("Provider '{$providerName}' not found");
        }

        if (! $provider->is_active) {
            Log::error("Provider '{$providerName}' is not active");
            throw new \Exception("Provider '{$providerName}' is not active");
        }

        $pingUrl = $provider->ping_url;
        if (! $pingUrl) {
            Log::error("No ping URL configured for provider '{$providerName}'");
            throw new \Exception("No ping URL configured for provider '{$providerName}'");
        }

        try {
            // Use AI Provider Factory to get models from API
            return $this->fetchModelsUsingAIProvider($provider);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve models from provider', [
                'provider_name' => $providerName,
                'provider_id' => $provider->id,
                'api_format' => $provider->api_format_id,
                'ping_url' => $pingUrl,
                'error' => $e->getMessage(),
                'status' => 'error',
            ]);
            throw $e;
        }
    }

    /**
     * Fetch models using the AI Provider Factory and providers
     *
     * @throws \Exception
     */
    private function fetchModelsUsingAIProvider(ProviderSetting $provider): array
    {
        try {
            // Get the AI provider instance
            $aiProvider = $this->aiProviderFactory->getProviderInterfaceById($provider->id);

            // Use the provider's fetchAvailableModelsFromAPI method
            return $aiProvider->fetchAvailableModelsFromAPI();

        } catch (\Exception $e) {
            // If AI Provider approach fails, fall back to direct HTTP approach
            Log::warning('AI Provider approach failed, falling back to direct HTTP: '.$e->getMessage());

            return $this->fetchModelsFromProviderDirect($provider);
        }
    }

    /**
     * Fallback method: Fetch models directly using HTTP
     * Kept for backward compatibility and as fallback
     *
     * @throws \Exception
     */
    private function fetchModelsFromProviderDirect(ProviderSetting $provider): array
    {
        $apiFormatName = $provider->apiFormat?->unique_name ?? $provider->provider_name;
        $pingUrl = $provider->ping_url;
        $apiKey = $provider->api_key;

        try {
            $result = [];

            // Different provider types may have different API formats
            switch ($apiFormatName) {
                case 'openai':
                case 'openai-api':
                    $result = $this->fetchOpenAIModels($pingUrl, $apiKey);
                    break;

                case 'openWebUi':
                case 'oobabooga':
                    $result = $this->fetchOpenWebUiModels($pingUrl, $apiKey);
                    break;

                case 'ollama':
                case 'ollama-api':
                    $result = $this->fetchOllamaModels($pingUrl, $apiKey);
                    break;

                case 'gwdg':
                case 'gwdg-api':
                    $result = $this->fetchGWDGModels($pingUrl, $apiKey);
                    break;

                case 'google':
                case 'google-generative-language-api':
                case 'google-vertex-ai-api':
                    $result = $this->fetchGoogleModels($pingUrl, $apiKey);
                    break;

                case 'anthropic':
                case 'anthropic-api':
                    $result = $this->fetchAnthropicModels($pingUrl, $apiKey);
                    break;

                default:
                    $result = $this->fetchGenericModels($pingUrl, $apiKey);
                    break;
            }

            return $result;
        } catch (\Exception $e) {
            throw new \Exception('Failed to fetch models: '.$e->getMessage());
        }
    }

    /**
     * Fetch OpenAI models.
     *
     * @throws \Exception
     */
    private function fetchOpenAIModels(string $pingUrl, ?string $apiKey): array
    {
        $startTime = microtime(true);
        $keyMask = $apiKey ? substr($apiKey, 0, 5).'...' : 'none';

        try {
            $headers = [];
            if ($apiKey) {
                $headers['Authorization'] = "Bearer {$apiKey}";
            }

            $response = Http::withHeaders($headers)->get($pingUrl);

            if (! $response->successful()) {
                $logData = [
                    'provider' => 'openai',
                    'url' => $pingUrl,
                    'api_key' => $keyMask,
                    'status' => 'error',
                    'http_status' => $response->status(),
                    'error' => 'HTTP request failed',
                    'response_body' => substr($response->body(), 0, 200),
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ];
                Log::error('OpenAI model fetch failed', $logData);
                throw new \Exception("Failed to fetch OpenAI models: HTTP {$response->status()}");
            }

            $data = $response->json();

            // Debug: Log the actual response structure from OpenAI API
            Log::info('OpenAI API Response Debug', [
                'provider' => 'openai',
                'url' => $pingUrl,
                'api_key' => $keyMask,
                'response_structure' => [
                    'type' => gettype($data),
                    'keys' => is_array($data) ? array_keys($data) : 'not_array',
                    'data_sample' => is_array($data) ? array_slice($data, 0, 2, true) : $data,
                ],
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $data;
        } catch (\Exception $e) {
            $logData = [
                'provider' => 'openai',
                'url' => $pingUrl,
                'api_key' => $keyMask,
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
            Log::error('OpenAI model fetch exception: '.json_encode($logData));
            throw $e;
        }
    }

    /**
     * Fetch OpenWebUi/Oobabooga models.
     *
     * @throws \Exception
     */
    private function fetchOpenWebUiModels(string $pingUrl, ?string $apiKey): array
    {
        $startTime = microtime(true);
        $keyMask = $apiKey ? substr($apiKey, 0, 5).'...' : 'none';

        try {
            $headers = [];
            if ($apiKey) {
                $headers['Authorization'] = "Bearer {$apiKey}";
            }

            $response = Http::withHeaders($headers)->get($pingUrl);

            if (! $response->successful()) {
                $logData = [
                    'provider' => 'openwebui',
                    'url' => $pingUrl,
                    'api_key' => $keyMask,
                    'status' => 'error',
                    'http_status' => $response->status(),
                    'error' => 'HTTP request failed',
                    'response_body' => substr($response->body(), 0, 200),
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ];
                Log::error('OpenWebUi model fetch failed', $logData);
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
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
            Log::error('OpenWebUi model fetch exception: '.json_encode($logData));
            throw $e;
        }
    }

    /**
     * Fetch Ollama models.
     *
     * @throws \Exception
     */
    private function fetchOllamaModels(string $pingUrl, ?string $apiKey): array
    {
        $startTime = microtime(true);
        $keyMask = $apiKey ? substr($apiKey, 0, 5).'...' : 'none';
        $logData = [
            'provider' => 'ollama',
            'url' => $pingUrl,
            'api_key' => $keyMask,
            'timestamp' => now()->toISOString(),
        ];

        try {
            $headers = [];
            if ($apiKey) {
                $headers['Authorization'] = "Bearer {$apiKey}";
            }

            $response = Http::withHeaders($headers)->get($pingUrl);

            if (! $response->successful()) {
                $logData['status'] = 'error';
                $logData['http_status'] = $response->status();
                $logData['error'] = 'HTTP request failed';
                $logData['response_body'] = substr($response->body(), 0, 200);
                $logData['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);

                Log::error('Ollama model fetch failed', $logData);
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
                            ' ('.$model['details']['parameter_size'].')' : '';
                        $family = isset($model['details']['family']) ? $model['details']['family'] : '';

                        $result[$modelId] = [
                            'id' => $modelId,
                            'name' => $modelId,
                            'displayName' => ($family ? $family.' ' : '').$modelId.$paramSize,
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
                            'parameter_size' => $model['details']['parameter_size'] ?? '',
                        ];
                    }
                }
            }

            return $result;
        } catch (\Exception $e) {
            $logData['status'] = 'error';
            $logData['error'] = $e->getMessage();
            $logData['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Ollama model fetch exception: '.json_encode($logData));
            throw $e;
        }
    }

    /**
     * Fetch GWDG models.
     *
     * @throws \Exception
     */
    private function fetchGWDGModels(string $pingUrl, ?string $apiKey): array
    {
        $startTime = microtime(true);
        $keyMask = $apiKey ? substr($apiKey, 0, 5).'...' : 'none';

        try {
            $headers = [];
            if ($apiKey) {
                $headers['Authorization'] = "Bearer {$apiKey}";
            }

            $response = Http::withHeaders($headers)->get($pingUrl);

            if (! $response->successful()) {
                $logData = [
                    'provider' => 'gwdg',
                    'url' => $pingUrl,
                    'api_key' => $keyMask,
                    'status' => 'error',
                    'http_status' => $response->status(),
                    'error' => 'HTTP request failed',
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ];
                Log::error('GWDG model fetch failed', $logData);
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
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
            Log::error('GWDG model fetch exception: '.json_encode($logData));
            throw $e;
        }
    }

    /**
     * Fetch Google models.
     *
     * @throws \Exception
     */
    private function fetchGoogleModels(string $pingUrl, ?string $apiKey): array
    {
        $startTime = microtime(true);
        $keyMask = $apiKey ? substr($apiKey, 0, 5).'...' : 'none';

        try {
            // Google API uses API key as query parameter, not header
            $headers = [
                'Content-Type' => 'application/json',
            ];

            // Add API key as query parameter
            $urlWithKey = $pingUrl;
            if ($apiKey) {
                $separator = strpos($pingUrl, '?') !== false ? '&' : '?';
                $urlWithKey = $pingUrl.$separator.'key='.$apiKey;
            }

            $response = Http::withHeaders($headers)->get($urlWithKey);

            if (! $response->successful()) {
                $logData = [
                    'provider' => 'google',
                    'url' => $pingUrl, // Don't log the full URL with API key
                    'api_key' => $keyMask,
                    'status' => 'error',
                    'http_status' => $response->status(),
                    'error' => 'HTTP request failed',
                    'response_body' => substr($response->body(), 0, 200),
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ];
                Log::error('Google model fetch failed', $logData);
                throw new \Exception("Failed to fetch Google models: HTTP {$response->status()}");
            }

            $data = $response->json();

            // Debug: Log the actual response structure from Google API
            Log::info('Google API Response Debug', [
                'provider' => 'google',
                'url' => $pingUrl,
                'api_key' => $keyMask,
                'response_structure' => [
                    'type' => gettype($data),
                    'keys' => is_array($data) ? array_keys($data) : 'not_array',
                    'data_sample' => is_array($data) ? array_slice($data, 0, 2, true) : $data,
                ],
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $data;
        } catch (\Exception $e) {
            $logData = [
                'provider' => 'google',
                'url' => $pingUrl,
                'api_key' => $keyMask,
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
            Log::error('Google model fetch exception: '.json_encode($logData));
            throw $e;
        }
    }

    /**
     * Fetch Anthropic models.
     *
     * @throws \Exception
     */
    private function fetchAnthropicModels(string $pingUrl, ?string $apiKey): array
    {
        $startTime = microtime(true);
        $keyMask = $apiKey ? substr($apiKey, 0, 5).'...' : 'none';

        try {
            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($apiKey) {
                // Anthropic uses x-api-key header instead of Authorization Bearer
                $headers['x-api-key'] = $apiKey;
                $headers['anthropic-version'] = '2023-06-01'; // Required by Anthropic API
            }

            $response = Http::withHeaders($headers)->get($pingUrl);

            if (! $response->successful()) {
                $logData = [
                    'provider' => 'anthropic',
                    'url' => $pingUrl,
                    'api_key' => $keyMask,
                    'status' => 'error',
                    'http_status' => $response->status(),
                    'error' => 'HTTP request failed',
                    'response_body' => substr($response->body(), 0, 200),
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ];
                Log::error('Anthropic model fetch failed', $logData);
                throw new \Exception("Failed to fetch Anthropic models: HTTP {$response->status()}");
            }

            $data = $response->json();

            // Debug: Log the actual response structure from Anthropic API
            Log::info('Anthropic API Response Debug', [
                'provider' => 'anthropic',
                'url' => $pingUrl,
                'api_key' => $keyMask,
                'response_structure' => [
                    'type' => gettype($data),
                    'keys' => is_array($data) ? array_keys($data) : 'not_array',
                    'data_sample' => is_array($data) ? array_slice($data, 0, 2, true) : $data,
                ],
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $data;
        } catch (\Exception $e) {
            $logData = [
                'provider' => 'anthropic',
                'url' => $pingUrl,
                'api_key' => $keyMask,
                'status' => 'error',
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
            Log::error('Anthropic model fetch exception: '.json_encode($logData));
            throw $e;
        }
    }

    /**
     * Fetch models from a generic provider.
     *
     * @throws \Exception
     */
    private function fetchGenericModels(string $pingUrl, ?string $apiKey): array
    {
        $startTime = microtime(true);
        $keyMask = $apiKey ? substr($apiKey, 0, 5).'...' : 'none';

        try {
            $headers = [];
            if ($apiKey) {
                $headers['Authorization'] = "Bearer {$apiKey}";
            }

            $response = Http::withHeaders($headers)->get($pingUrl);

            if (! $response->successful()) {
                $logData = [
                    'provider' => 'generic',
                    'url' => $pingUrl,
                    'api_key' => $keyMask,
                    'status' => 'error',
                    'http_status' => $response->status(),
                    'error' => 'HTTP request failed',
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ];
                Log::error('Generic model fetch failed', $logData);
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
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
            Log::error('Generic model fetch exception: '.json_encode($logData));
            throw $e;
        }
    }

    /**
     * Deletes a language model from the database
     *
     * @param  int  $id  The ID of the model to delete
     */
    public function deleteModel(int $id): bool
    {
        try {
            $model = LanguageModel::find($id);

            if (! $model) {
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
            Log::error("Error deleting model with ID {$id}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Import models from API response into the database
     *
     * @param  int  $providerId  The provider ID
     * @param  array  $apiResponse  The API response containing models
     * @return array Import results with success status and statistics
     */
    public function importModelsFromApiResponse(int $providerId, array $apiResponse): array
    {
        try {
            $totalModels = 0;
            $importedModels = 0;
            $updatedModels = 0;
            $errors = [];

            // Get the provider settings
            $provider = ProviderSetting::find($providerId);
            if (! $provider) {
                return [
                    'success' => false,
                    'error' => "Provider with ID {$providerId} not found",
                    'total' => 0,
                    'imported' => 0,
                    'updated' => 0,
                ];
            }

            // Extract models from API response
            $models = [];
            if (isset($apiResponse['data']) && is_array($apiResponse['data'])) {
                // OpenAI format: {"object": "list", "data": [...]}
                $models = $apiResponse['data'];
            } elseif (isset($apiResponse['models']) && is_array($apiResponse['models'])) {
                // Alternative format: {"models": [...]}
                $models = $apiResponse['models'];
            } elseif (is_array($apiResponse) && ! empty($apiResponse)) {
                // Direct array format: [...]
                $models = $apiResponse;
            }

            $totalModels = count($models);

            if (empty($models)) {
                return [
                    'success' => true,
                    'total' => 0,
                    'imported' => 0,
                    'updated' => 0,
                    'message' => 'No models found in API response',
                ];
            }

            foreach ($models as $modelData) {
                try {
                    // Extract model information
                    $modelId = $modelData['id'] ?? null;
                    if (! $modelId) {
                        $errors[] = 'Model missing ID field';

                        continue;
                    }

                    // Determine if model should be streamable (default true unless specified)
                    $streamable = true;

                    // For OpenAI reasoning models (o1, o3 series), disable streaming
                    if (str_contains($modelId, 'o1') || str_contains($modelId, 'o3')) {
                        $streamable = false;
                    }

                    // Create a human-readable label
                    $label = $this->generateModelLabel($modelId, $modelData);

                    // Check if model already exists
                    $existingModel = LanguageModel::where('model_id', $modelId)
                        ->where('provider_id', $providerId)
                        ->first();

                    $modelAttributes = [
                        'provider_id' => $providerId,
                        'label' => $label,
                        'streamable' => $streamable,
                        'is_active' => true,
                        'display_order' => 1000, // Default order, can be adjusted later
                        'information' => json_encode($modelData),
                        'settings' => json_encode([]),
                    ];

                    if ($existingModel) {
                        // Update existing model
                        $existingModel->update($modelAttributes);
                        $updatedModels++;
                        Log::info("Updated model: {$modelId} for provider {$provider->provider_name}");
                    } else {
                        // Create new model
                        $modelAttributes['model_id'] = $modelId;
                        LanguageModel::create($modelAttributes);
                        $importedModels++;
                        Log::info("Imported new model: {$modelId} for provider {$provider->provider_name}");
                    }

                } catch (\Exception $e) {
                    $errors[] = "Error processing model {$modelId}: ".$e->getMessage();
                    Log::error("Error importing model {$modelId}: ".$e->getMessage());
                }
            }

            $success = $importedModels > 0 || $updatedModels > 0 || empty($errors);

            return [
                'success' => $success,
                'total' => $totalModels,
                'imported' => $importedModels,
                'updated' => $updatedModels,
                'errors' => $errors,
            ];

        } catch (\Exception $e) {
            Log::error('Error importing models from API response: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'total' => 0,
                'imported' => 0,
                'updated' => 0,
            ];
        }
    }

    /**
     * Generate a human-readable label for a model
     */
    private function generateModelLabel(string $modelId, array $modelData): string
    {
        // Use display name if available
        if (isset($modelData['display_name'])) {
            return $modelData['display_name'];
        }

        // Use name if available
        if (isset($modelData['name']) && $modelData['name'] !== $modelId) {
            return $modelData['name'];
        }

        // For OpenAI models, create more readable labels
        $label = $modelId;

        // Handle common OpenAI model patterns
        $patterns = [
            '/^gpt-4o-mini/' => 'OpenAI GPT-4o Mini',
            '/^gpt-4o/' => 'OpenAI GPT-4o',
            '/^gpt-4-turbo/' => 'OpenAI GPT-4 Turbo',
            '/^gpt-4/' => 'OpenAI GPT-4',
            '/^gpt-3\.5-turbo/' => 'OpenAI GPT-3.5 Turbo',
            '/^o1-mini/' => 'OpenAI o1-mini',
            '/^o1-pro/' => 'OpenAI o1-pro',
            '/^o1/' => 'OpenAI o1',
            '/^o3-mini/' => 'OpenAI o3-mini',
            '/^o3-pro/' => 'OpenAI o3-pro',
            '/^o3-deep-research/' => 'OpenAI o3 Deep Research',
            '/^o3/' => 'OpenAI o3',
            '/^claude-/' => 'Anthropic Claude',
            '/^gemini-/' => 'Google Gemini',
        ];

        foreach ($patterns as $pattern => $replacement) {
            if (preg_match($pattern, $modelId)) {
                $label = $replacement;

                // Add date suffix if present
                if (preg_match('/(\d{4}-\d{2}-\d{2})/', $modelId, $matches)) {
                    $label .= ' ('.$matches[1].')';
                }
                break;
            }
        }

        return $label;
    }
}
