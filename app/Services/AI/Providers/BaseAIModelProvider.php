<?php

namespace App\Services\AI\Providers;

use App\Models\LanguageModel;
use App\Models\ProviderSetting;
use App\Services\AI\Interfaces\AIModelProviderInterface;
use Illuminate\Support\Facades\Http;
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
     */
    public function __construct(array $config)
    {
        $this->config = $config;

        // Try to extract the provider identifier from the configuration
        // Use provider_name if available, otherwise fall back to other fields
        if (! isset($this->providerId)) {
            if (isset($config['provider_name'])) {
                $this->providerId = $config['provider_name'];
            } elseif (isset($config['api_format'])) {
                $this->providerId = $config['api_format'];
            }
        }
    }

    /**
     * Extract usage information from the response data
     *
     * @param  array  $data  Response data
     * @return array|null Usage data or null if not available
     */
    protected function extractUsage(array $data): ?array
    {
        return null;
    }

    /**
     * Get details for a specific model from the database
     */
    public function getModelDetails(string $modelId): array
    {
        try {
            // Read model data from the database with API format information
            $model = LanguageModel::select('language_models.*', 'provider_settings.provider_name', 'api_formats.unique_name as api_format_name')
                ->join('provider_settings', 'language_models.provider_id', '=', 'provider_settings.id')
                ->leftJoin('api_formats', 'provider_settings.api_format_id', '=', 'api_formats.id')
                ->where('language_models.model_id', $modelId)
                ->where('language_models.is_active', true)
                ->first();

            if (! $model) {
                Log::warning("Model not found in database: $modelId");

                return [
                    'id' => $modelId,
                    'label' => $modelId, // Fallback: Use ID as label
                    'streamable' => false,
                    'api_format' => $this->getProviderId(),
                    'provider_name' => $this->getProviderId(),
                ];
            }

            $details = [
                'id' => $model->model_id,
                'label' => $model->label,
                'streamable' => $model->streamable,
                'api_format' => $model->api_format_name ?? $model->provider_name,
                'provider_name' => $model->provider_name,
            ];

            // Add additional model information if available
            if (! empty($model->information)) {
                $information = is_array($model->information) ?
                              $model->information :
                              json_decode($model->information, true);

                if (is_array($information)) {
                    $details = array_merge($details, $information);
                }
            }

            // Add settings if available
            if (! empty($model->settings)) {
                $settings = is_array($model->settings) ?
                          $model->settings :
                          json_decode($model->settings, true);

                if (is_array($settings)) {
                    $details['settings'] = $settings;
                }
            }

            return $details;
        } catch (\Exception $e) {
            Log::error('Error getting model details: '.$e->getMessage());

            // Simple fallback response
            return [
                'id' => $modelId,
                'label' => $modelId,
                'streamable' => false,
                'api_format' => $this->getProviderId(),
                'provider_name' => $this->getProviderId(),
            ];
        }
    }

    /**
     * Get all available models for this provider from the database
     */
    public function getAvailableModels(): array
    {
        try {
            $providerId = $this->getProviderId();

            // Get provider from the database by provider_name (since providerId is the name)
            $provider = ProviderSetting::where('provider_name', $providerId)
                ->where('is_active', true)
                ->first();

            if (! $provider) {
                Log::warning("Provider not found in database: $providerId");

                return [];
            }

            // Retrieve all active models for this provider with API format information
            $models = LanguageModel::select('language_models.*', 'api_formats.unique_name as api_format_name')
                ->leftJoin('provider_settings', 'language_models.provider_id', '=', 'provider_settings.id')
                ->leftJoin('api_formats', 'provider_settings.api_format_id', '=', 'api_formats.id')
                ->where('language_models.provider_id', $provider->id)
                ->where('language_models.is_active', true)
                ->orderBy('display_order')
                ->get();

            $modelsList = [];

            foreach ($models as $model) {
                $modelData = [
                    'id' => $model->model_id,
                    'label' => $model->label,
                    'streamable' => $model->streamable,
                    'api_format' => $model->api_format_name ?? $provider->provider_name,
                    'provider_name' => $provider->provider_name,
                ];

                // Extract status from the information field if available
                if (! empty($model->information)) {
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
            Log::error('Error getting available models: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Get the provider ID
     */
    public function getProviderId(): string
    {
        return $this->providerId ?? 'unknown_provider';
    }

    /**
     * Check if a model supports streaming
     *
     * @param  string  $modelId  Model identifier
     * @return bool True if streaming is supported
     */
    public function supportsStreaming(string $modelId): bool
    {
        return $this->getModelDetails($modelId)['streamable'] ?? false;
    }

    /**
     * Establish a connection to the AI provider's API
     *
     * @param  array  $payload  The formatted payload
     * @param  callable|null  $streamCallback  Callback for streaming responses
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
     * Fetch available models from the provider's API
     * Base implementation using HTTP client - providers can override this
     *
     * @return array Raw API response containing models
     *
     * @throws \Exception
     */
    public function fetchAvailableModelsFromAPI(): array
    {
        try {
            $provider = $this->getProviderFromDatabase();
            if (! $provider) {
                throw new \Exception("Provider not found in database: {$this->getProviderId()}");
            }

            $pingUrl = $provider->ping_url;
            if (! $pingUrl) {
                throw new \Exception("No ping URL configured for provider: {$this->getProviderId()}");
            }

            $headers = $this->getModelsApiHeaders();

            $response = Http::withHeaders($headers)->get($pingUrl);

            if (! $response->successful()) {
                throw new \Exception("Failed to fetch models: HTTP {$response->status()}");
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Error fetching models from API for provider {$this->getProviderId()}: ".$e->getMessage());
            throw $e;
        }
    }

    /**
     * Get HTTP headers for models API requests
     * Providers can override this for specific authentication methods
     */
    protected function getModelsApiHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if (! empty($this->config['api_key'])) {
            $headers['Authorization'] = 'Bearer '.$this->config['api_key'];
        }

        return $headers;
    }

    /**
     * Build URL for connection testing with provider-specific authentication
     * Base implementation just returns the URL, providers can override for query parameters
     */
    public function buildConnectionTestUrl(string $baseUrl): string
    {
        return $baseUrl;
    }

    /**
     * Get headers for connection testing
     * Uses the same logic as models API headers
     */
    public function getConnectionTestHeaders(): array
    {
        $headers = $this->getModelsApiHeaders();
        
        // Add Accept header for connection tests
        $headers['Accept'] = 'application/json';
        
        return $headers;
    }

    /**
     * Get the provider settings from database
     * Providers should implement this method
     */
    protected function getProviderFromDatabase(): ?ProviderSetting
    {
        return ProviderSetting::where('provider_name', $this->getProviderId())
            ->where('is_active', true)
            ->first();
    }

    /**
     * Set up common HTTP headers for API requests
     *
     * @param  bool  $isStreaming  Whether this is a streaming request
     */
    protected function getHttpHeaders(bool $isStreaming = false): array
    {
        $headers = [
            'Content-Type: application/json',
        ];

        // Add authorization header if API key is present
        if (! empty($this->config['api_key'])) {
            $headers[] = 'Authorization: Bearer '.$this->config['api_key'];
        }

        return $headers;
    }

    /**
     * Set common cURL options for all requests
     *
     * @param  resource  $ch  cURL resource
     * @param  array  $payload  Request payload
     * @param  array  $headers  HTTP headers
     */
    protected function setCommonCurlOptions($ch, array $payload, array $headers): void
    {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        // Apply proxy configuration if available
        $this->setProxyCurlOptions($ch);
    }

    /**
     * Set proxy options for cURL if configured in environment
     *
     * @param  resource  $ch  cURL resource
     */
    protected function setProxyCurlOptions($ch): void
    {
        // Check for proxy environment variables
        if (getenv('HTTP_PROXY') || getenv('http_proxy')) {
            $proxy = getenv('HTTP_PROXY') ?: getenv('http_proxy');
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }
        
        if (getenv('HTTPS_PROXY') || getenv('https_proxy')) {
            $httpsProxy = getenv('HTTPS_PROXY') ?: getenv('https_proxy');
            curl_setopt($ch, CURLOPT_PROXY, $httpsProxy);
        }
        
        // Check for no proxy configuration
        if (getenv('NO_PROXY') || getenv('no_proxy')) {
            $noProxy = getenv('NO_PROXY') ?: getenv('no_proxy');
            curl_setopt($ch, CURLOPT_NOPROXY, $noProxy);
        }
    }

    /**
     * Set up streaming-specific cURL options
     *
     * @param  resource  $ch  cURL resource
     * @param  callable  $streamCallback  Callback for processing chunks
     */
    protected function setStreamingCurlOptions($ch, callable $streamCallback): void
    {
        // Set timeout parameters for streaming
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minutes max execution time
        curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1);
        curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 20);

        // Process each chunk as it arrives
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($streamCallback) {
            if (connection_aborted()) {
                return 0;
            }

            $streamCallback($data);

            if (config('logging.triggers.curl_return_object')) {
                Log::debug($data);
            }
            if (ob_get_length()) {
                ob_flush();
            }
            flush();

            return strlen($data);
        });
    }

    /**
     * Default implementation of formatStreamMessages for most providers
     * 
     * This is the STANDARD implementation that wraps formatStreamChunk() output
     * into a complete frontend-ready message. Most providers can use this default.
     * 
     * Key differences from formatStreamChunk():
     * - formatStreamChunk(): Returns RAW data extraction from SSE chunk
     * - formatStreamMessages(): Returns COMPLETE messages ready for frontend
     * 
     * Override this method if your provider needs special streaming behavior:
     * - Multiple messages per chunk (like Google's content+completion split)
     * - Special message formatting
     * - Provider-specific UI requirements
     * 
     * @param string $chunk Raw SSE chunk data
     * @param array $messageContext UI context from StreamController (author, model, etc.)
     * @return array Single message in array: [['author'=>..., 'model'=>..., 'isDone'=>..., 'content'=>..., 'usage'=>...]]
     */
    public function formatStreamMessages(string $chunk, array $messageContext): array
    {
        $formatted = $this->formatStreamChunk($chunk);
        
        return [[
            'author' => $messageContext['author'],
            'model' => $messageContext['model'],
            'isDone' => $formatted['isDone'],
            'content' => json_encode($formatted['content']),
            'usage' => $formatted['usage'],
        ]];
    }
}
