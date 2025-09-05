<?php

namespace App\Services\AI\Providers;

use App\Models\ProviderSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\Citations\CitationService;

class GoogleProvider extends BaseAIModelProvider
{
    /**
     * Citation service for unified citation formatting
     */
    private CitationService $citationService;

    /**
     * Constructor for GoogleProvider
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->providerId = 'Google'; // Muss mit Datenbank übereinstimmen (case-sensitive)
        $this->citationService = app(CitationService::class);
    }

    /**
     * Get the provider settings from database
     */
    protected function getProviderFromDatabase(): ?ProviderSetting
    {
        return ProviderSetting::where('provider_name', $this->providerId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Format the raw payload for Google API
     */
    public function formatPayload(array $rawPayload): array
    {
        $messages = $rawPayload['messages'];
        $modelId = $rawPayload['model'];

        // Extract system prompt from first message item
        $systemInstruction = [];
        if (isset($messages[0]) && $messages[0]['role'] === 'system') {
            $systemInstruction = [
                'parts' => [
                    'text' => $messages[0]['content']['text'] ?? '',
                ],
            ];
            array_shift($messages);
        }

        // Format messages for Google
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [
                    [
                        'text' => $message['content']['text'],
                    ],
                ],
            ];
        }

        $payload = [
            'model' => $modelId,
            'contents' => $formattedMessages,
            'stream' => $rawPayload['stream'] && $this->supportsStreaming($modelId),
        ];

        // Only add system_instruction if it's not empty
        if (! empty($systemInstruction)) {
            $payload['system_instruction'] = $systemInstruction;
        }

        // Set complete optional fields with content (default values if not present in $rawPayload)
        $payload['safetySettings'] = $rawPayload['safetySettings'] ?? [
            [
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_ONLY_HIGH',
            ],
        ];

        $payload['generationConfig'] = $rawPayload['generationConfig'] ?? [
            // 'stopSequences' => ["Title"],
            'temperature' => 1.0,
            'maxOutputTokens' => 800,
            'topP' => 0.8,
            'topK' => 10,
        ];

        // Web Search Tool - controlled purely by model settings
        // No provider-level check needed, only model-specific settings matter
        $supportsSearch = $this->modelSupportsSearch($modelId);

        if ($supportsSearch) {
            $payload['tools'] = $rawPayload['tools'] ?? [
                [
                    'google_search' => new \stdClass,
                ],
            ];
        }

        return $payload;
    }

    /**
     * Format the complete response from Google
     *
     * @param  mixed  $response
     */
    public function formatResponse($response): array
    {
        $responseContent = $response->getContent();
        $jsonContent = json_decode($responseContent, true);

        $content = $jsonContent['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $rawGroundingMetadata = $jsonContent['candidates'][0]['groundingMetadata'] ?? '';
        
        // Format citations using unified service
        $groundingMetadata = '';
        if (!empty($rawGroundingMetadata)) {
            $formattedCitations = $this->citationService->formatCitations('google', $rawGroundingMetadata, $content);
            $groundingMetadata = $formattedCitations;
        }

        return [
            'content' => [
                'text' => $content,
                'groundingMetadata' => $groundingMetadata,
            ],
            'usage' => $this->extractUsage($jsonContent),
        ];
    }

    /**
     * Format a single chunk from a streaming response from Google
     */
    public function formatStreamChunk(string $chunk): array
    {

        $jsonChunk = json_decode($chunk, true);

        $content = '';
        $groundingMetadata = '';
        $isDone = false;
        $usage = null;

        // Extract content if available
        if (isset($jsonChunk['candidates'][0]['content']['parts'][0]['text'])) {
            $content = $jsonChunk['candidates'][0]['content']['parts'][0]['text'];
        }

        // Add search results
        if (isset($jsonChunk['candidates'][0]['groundingMetadata'])) {
            $rawGroundingMetadata = $jsonChunk['candidates'][0]['groundingMetadata'];
            
            // Format citations using unified service
            $formattedCitations = $this->citationService->formatCitations('google', $rawGroundingMetadata, $content);
            $groundingMetadata = $formattedCitations;
        }

        // Check for completion
        if (isset($jsonChunk['candidates'][0]['finishReason']) &&
            $jsonChunk['candidates'][0]['finishReason'] !== 'FINISH_REASON_UNSPECIFIED') {
            $isDone = true;
        }

        // Extract usage if available
        if (isset($jsonChunk['usageMetadata'])) {
            $usage = $this->extractUsage($jsonChunk);
        }

        return [
            'content' => [
                'text' => $content,
                'groundingMetadata' => $groundingMetadata,
            ],
            'isDone' => $isDone,
            'usage' => $usage,
        ];
    }

    /**
     * Extract usage information from Google response
     */
    protected function extractUsage(array $data): ?array
    {
        if (empty($data['usageMetadata'])) {
            return null;
        }
        // fix duplicate usage log entries
        if (! empty($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] === 'STOP') {
            return [
                'prompt_tokens' => $data['usageMetadata']['promptTokenCount'] ?? 0,
                'completion_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? 0,
            ];
        }

        return null;
    }

    /**
     * Override common HTTP headers for Google API requests without Authorization header
     *
     * @param  bool  $isStreaming  Whether this is a streaming request
     */
    protected function getHttpHeaders(bool $isStreaming = false): array
    {
        $headers = [
            'Content-Type: application/json',
        ];

        return $headers;
    }

    /**
     * Make a non-streaming request to the Google API
     *
     * @return mixed
     */
    public function makeNonStreamingRequest(array $payload)
    {
        // Ensure stream is set to false
        $payload['stream'] = false;

        // Get the chat endpoint URL from database configuration
        $provider = $this->getProviderFromDatabase();
        if ($provider) {
            $chatEndpoint = $provider->apiFormat?->getEndpoint('chat.create');
            if ($chatEndpoint) {
                $baseUrl = $provider->apiFormat->base_url;

                // Handle model ID - Google API expects models/model-name format
                $modelId = $payload['model'];

                // If model ID already starts with "models/", use it as is
                // Otherwise, prepend "models/" to it
                if (! str_starts_with($modelId, 'models/')) {
                    $modelId = 'models/'.$modelId;
                }

                // For Google API, if the endpoint path contains "/models/{model}",
                // and our model ID already has "models/", we need to adjust
                $path = $chatEndpoint->path;
                if (str_contains($path, '/models/{model}') && str_starts_with($modelId, 'models/')) {
                    // Remove the extra "models/" from the path since model ID already has it
                    $path = str_replace('/models/{model}', '/{model}', $path);
                }

                $path = str_replace('{model}', $modelId, $path);
                $url = rtrim($baseUrl, '/').$path.'?key='.$this->config['api_key'];
            } else {
                throw new \Exception('Chat endpoint not found for Google provider');
            }
        } else {
            throw new \Exception('Google provider configuration not found in database');
        }

        // Extract just the necessary parts for Google's API
        $requestPayload = [
            'contents' => $payload['contents'],
        ];

        // Only add system_instruction if it exists
        if (isset($payload['system_instruction'])) {
            $requestPayload['system_instruction'] = $payload['system_instruction'];
        }

        // Add aditional config parameters if present
        if (isset($payload['safetySettings'])) {
            $requestPayload['safetySettings'] = $payload['safetySettings'];
        }
        if (isset($payload['generationConfig'])) {
            $requestPayload['generationConfig'] = $payload['generationConfig'];
        }
        if (isset($payload['tools'])) {
            $requestPayload['tools'] = $payload['tools'];
        }

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        // Set common cURL options
        $this->setCommonCurlOptions($ch, $requestPayload, $this->getHttpHeaders());

        // Execute the request
        $response = curl_exec($ch);

        // Handle errors
        if (curl_errno($ch)) {
            $error = 'Error: '.curl_error($ch);
            curl_close($ch);

            return response()->json(['error' => $error], 500);
        }

        curl_close($ch);

        return response($response)->header('Content-Type', 'application/json');
    }

    /**
     * Make a streaming request to the Google API
     *
     * @param  array  $payload  The formatted payload
     * @param  callable  $streamCallback  Callback for streaming responses
     * @return void
     */
    public function makeStreamingRequest(array $payload, callable $streamCallback)
    {
        // Get the streaming endpoint URL from database configuration
        $provider = $this->getProviderFromDatabase();
        if ($provider) {
            $streamEndpoint = $provider->apiFormat?->getEndpoint('chat.stream');
            if ($streamEndpoint) {
                $baseUrl = $provider->apiFormat->base_url;

                // Handle model ID - Google API expects models/model-name format
                $modelId = $payload['model'];

                // If model ID already starts with "models/", use it as is
                // Otherwise, prepend "models/" to it
                if (! str_starts_with($modelId, 'models/')) {
                    $modelId = 'models/'.$modelId;
                }

                // For Google API, if the endpoint path contains "/models/{model}",
                // and our model ID already has "models/", we need to adjust
                $path = $streamEndpoint->path;
                if (str_contains($path, '/models/{model}') && str_starts_with($modelId, 'models/')) {
                    // Remove the extra "models/" from the path since model ID already has it
                    $path = str_replace('/models/{model}', '/{model}', $path);
                }

                $path = str_replace('{model}', $modelId, $path);
                $url = rtrim($baseUrl, '/').$path.'?alt=sse&key='.$this->config['api_key'];
            } else {
                throw new \Exception('Stream endpoint not found for Google provider');
            }
        } else {
            throw new \Exception('Google provider configuration not found in database');
        }

        // Extract necessary parts for Google's API
        $requestPayload = [
            'contents' => $payload['contents'],
        ];

        // Only add system_instruction if it exists
        if (isset($payload['system_instruction'])) {
            $requestPayload['system_instruction'] = $payload['system_instruction'];
        }

        // Add aditional config parameters if present
        if (isset($payload['safetySettings'])) {
            $requestPayload['safetySettings'] = $payload['safetySettings'];
        }
        if (isset($payload['generationConfig'])) {
            $requestPayload['generationConfig'] = $payload['generationConfig'];
        }
        if (isset($payload['tools'])) {
            $requestPayload['tools'] = $payload['tools'];
        }

        set_time_limit(120);

        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        // Set common cURL options
        $this->setCommonCurlOptions($ch, $requestPayload, $this->getHttpHeaders(true));

        // Set streaming-specific options
        $this->setStreamingCurlOptions($ch, $streamCallback);

        // Execute the cURL session
        curl_exec($ch);

        // Handle errors
        if (curl_errno($ch)) {
            $streamCallback('Error: '.curl_error($ch));
            if (ob_get_length()) {
                ob_flush();
            }
            flush();
        }

        curl_close($ch);

        // Flush any remaining data
        if (ob_get_length()) {
            ob_flush();
        }
        flush();
    }

    /**
     * Get HTTP headers for models API requests
     * Google uses API key as query parameter, not header
     */
    protected function getModelsApiHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Build URL for connection testing with API key as query parameter
     */
    public function buildConnectionTestUrl(string $baseUrl): string
    {
        if (! empty($this->config['api_key'])) {
            $separator = strpos($baseUrl, '?') !== false ? '&' : '?';
            return $baseUrl . $separator . 'key=' . $this->config['api_key'];
        }
        
        return $baseUrl;
    }

    /**
     * Fetch available models from Google API
     * Overrides base implementation to handle API key as query parameter
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

            // Add API key as query parameter for Google
            $urlWithKey = $pingUrl;
            if (! empty($this->config['api_key'])) {
                $separator = strpos($pingUrl, '?') !== false ? '&' : '?';
                $urlWithKey = $pingUrl.$separator.'key='.$this->config['api_key'];
            }

            $response = Http::withHeaders($headers)->get($urlWithKey);

            if (! $response->successful()) {
                throw new \Exception("Failed to fetch models: HTTP {$response->status()}");
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Error fetching models from Google API for provider {$this->getProviderId()}: ".$e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if a model supports Google Search functionality
     */
    private function modelSupportsSearch(string $modelId): bool
    {
        // Check database model settings (same logic as Anthropic Provider)
        try {
            $modelDetails = $this->getModelDetails($modelId);
            
            if (is_array($modelDetails)) {
                // Check if search_tool is in the settings sub-array (primary location)
                if (isset($modelDetails['settings']['search_tool'])) {
                    return (bool) $modelDetails['settings']['search_tool'];
                }

                // Fallback: Check if search_tool is in the top level (from information field)
                if (isset($modelDetails['search_tool'])) {
                    return (bool) $modelDetails['search_tool'];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get model details for search check: '.$e->getMessage());
        }

        // No fallback - only use database settings
        return false;
    }
}
