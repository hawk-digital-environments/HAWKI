<?php

namespace App\Services\AI\Providers;

use App\Models\ProviderSetting;
use App\Services\Citations\CitationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI Responses API Provider
 *
 * Provides access to OpenAI's Responses API for advanced reasoning and structured outputs.
 * Note: Only supports gpt-4 and later model families.
 * The o1/o3 reasoning models are not compatible with the Responses API.
 */
class OpenAIResponsesProvider extends BaseAIModelProvider
{
    /**
     * Citation service for unified citation formatting
     */
    private CitationService $citationService;

    /**
     * Accumulated web search sources (from web_search_call output)
     */
    protected array $webSearchSources = [];

    /**
     * Accumulated search queries for citation metadata
     */
    protected array $searchQueries = [];

    /**
     * Accumulated citations from annotation events
     */
    protected array $annotations = [];

    /**
     * Accumulated message text for citation processing
     */
    protected string $fullMessageText = '';

    /**
     * Constructor for OpenAIResponsesProvider
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->providerId = 'openai_responses';
        $this->citationService = app(CitationService::class);
    }

    /**
     * Get the provider ID from config if available, otherwise use default
     */
    public function getProviderId(): string
    {
        return $this->config['provider_name'] ?? $this->providerId ?? 'unknown_provider';
    }

    /**
     * Get the chat endpoint URL from the provider's API format configuration
     */
    protected function getChatEndpointUrl(): string
    {
        $provider = $this->getProviderFromDatabase();

        return $provider ? $provider->chat_url : ($this->config['api_url'] ?? 'https://api.openai.com/v1/responses');
    }

    /**
     * Get the models endpoint URL from the provider's API format configuration
     */
    protected function getModelsEndpointUrl(): string
    {
        $provider = $this->getProviderFromDatabase();

        return $provider ? $provider->ping_url : ($this->config['ping_url'] ?? 'https://api.openai.com/v1/models');
    }

    /**
     * Get the provider settings from database
     */
    protected function getProviderFromDatabase(): ?ProviderSetting
    {
        // Use database ID if available in config, otherwise fall back to provider_name
        if (isset($this->config['provider_id'])) {
            return ProviderSetting::where('id', $this->config['provider_id'])
                ->where('is_active', true)
                ->first();
        }

        // Fall back to provider_name search
        return ProviderSetting::where('provider_name', $this->config['provider_name'] ?? $this->providerId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Map messages for OpenAI Responses API format
     */
    /**
     * Map messages for OpenAI Responses API format
     */
    public function mapMessages(array $messages): array
    {
        $mapped = [];

        foreach ($messages as $message) {
            $mapped[] = [
                // change: map "system" -> "developer"
                'role' => ($message['role'] === 'system') ? 'developer' : $message['role'],
                'content' => $message['content'],
                'auxiliaries' => $message['auxiliaries'] ?? [],
            ];
        }

        return $mapped;
    }

    /**
     * Handle model-specific formatting for messages
     */
    protected function handleModelSpecificFormatting(string $modelId, array $messages): array
    {
        // Apply any model-specific transformations if needed
        return $this->mapMessages($messages);
    }

    /**
     * Format the raw payload for OpenAI Responses API
     */
    public function formatPayload(array $rawPayload): array
    {
        $messages = $rawPayload['messages'];
        $modelId = $rawPayload['model'];

        // Handle special cases for specific models
        $messages = $this->handleModelSpecificFormatting($modelId, $messages);

        // Separate system messages as instructions and convert rest to input
        $instructions = null;
        $input = [];

        foreach ($messages as $message) {
            // Handle different content formats
            if (is_array($message['content'])) {
                $contentText = $message['content']['text'] ?? '';
            } else {
                $contentText = $message['content'] ?? '';
            }

            // Extract system messages as instructions (converted to developer for Responses API)
            if ($message['role'] === 'developer') {
                if ($instructions === null) {
                    $instructions = $contentText;
                } else {
                    $instructions .= "\n\n".$contentText;
                }

                continue;
            }

            $input[] = [
                'role' => $message['role'],
                'content' => $contentText,
            ];

            // Handle auxiliaries (reasoning from previous responses)
            $auxiliaries = $message['auxiliaries'] ?? [];
            foreach ($auxiliaries as $aux) {
                if ($aux['type'] == 'openAiResponsesSpecific') {
                    $modelSpecific = json_decode($aux['content'], true);
                    $reasoning = $modelSpecific['reasoning'] ?? [];
                    foreach ($reasoning as $reasoningItem) {
                        $input[] = $reasoningItem;
                    }
                }
            }
        }

        // Build payload for Responses endpoint
        $payload = [
            'model' => $modelId,
            'stream' => ! empty($rawPayload['stream']) && $this->supportsStreaming($modelId),
            'store' => false, // Always false for data safety/privacy
        ];

        // Add instructions if we have system messages
        if ($instructions !== null) {
            $payload['instructions'] = $instructions;
        }

        // Add input - can be string or array
        if (count($input) === 1 && $input[0]['role'] === 'user') {
            // Single user message - use string format for simplicity
            $payload['input'] = $input[0]['content'];
        } else {
            // Multiple messages or complex conversation - use array format
            $payload['input'] = $input;
        }

        // Get model configuration from provider settings
        $modelDetails = $this->getModelDetails($modelId);
        $provider = $this->getProviderFromDatabase();

        // Set the reasoning effort if configured
        if (isset($modelDetails['reasoning_effort'])) {
            $payload['reasoning'] = ['effort' => $modelDetails['reasoning_effort']];
        }

        // Keep encrypted reasoning tokens if requested (for ZDR compliance)
        if ($provider) {
            $additionalSettings = is_string($provider->additional_settings)
                ? json_decode($provider->additional_settings, true)
                : $provider->additional_settings;

            if (isset($additionalSettings['keep_reasoning_tokens']) && $additionalSettings['keep_reasoning_tokens']) {
                $payload['include'] = ['reasoning.encrypted_content', 'web_search_call.action.sources'];
            } else {
                // Always include web search sources for citation processing
                $payload['include'] = ['web_search_call.action.sources'];
            }
        }        // Add previous response ID for multi-turn conversations if available
        if (isset($rawPayload['previous_response_id'])) {
            $payload['previous_response_id'] = $rawPayload['previous_response_id'];
        }

        // Add structured outputs support (text.format instead of response_format)
        if (isset($rawPayload['response_format'])) {
            $payload['text'] = [
                'format' => $rawPayload['response_format'],
            ];
        }

        // Add tools support (including web search)
        $supportsSearch = $this->modelSupportsSearch($modelId);
        if ($supportsSearch) {
            // Add web search tool if not explicitly provided
            if (! isset($rawPayload['tools'])) {
                $payload['tools'] = [
                    ['type' => 'web_search'],
                ];
            } else {
                $payload['tools'] = $rawPayload['tools'];
            }
        } elseif (isset($rawPayload['tools'])) {
            // Only add tools if model supports them and they were explicitly provided
            $payload['tools'] = $rawPayload['tools'];
        }

        return $payload;
    }

    /**
     * Format the complete response from Responses API
     *
     * @param  mixed  $response
     */
    public function formatResponse($response): array
    {
        $responseContent = $response->body();
        $jsonContent = json_decode($responseContent, true);

        $texts = [];
        $reasoning = [];
        $citations = [];

        // Reset web search sources for this response
        $this->webSearchSources = [];

        // Responses API returns an 'output' array with different item types
        if (! empty($jsonContent['output']) && is_array($jsonContent['output'])) {
            foreach ($jsonContent['output'] as $outputItem) {
                switch ($outputItem['type']) {
                    case 'message':
                        // Extract text content from message items
                        if (! empty($outputItem['content']) && is_array($outputItem['content'])) {
                            foreach ($outputItem['content'] as $contentItem) {
                                if ($contentItem['type'] === 'output_text') {
                                    $texts[] = $contentItem['text'];

                                    // Extract citations from annotations
                                    if (isset($contentItem['annotations'])) {
                                        foreach ($contentItem['annotations'] as $annotation) {
                                            if ($annotation['type'] === 'url_citation') {
                                                $citations[] = $annotation;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        break;

                    case 'web_search_call':
                        // Handle web search call results
                        if (isset($outputItem['status']) && $outputItem['status'] === 'completed') {
                            $this->parseWebSearchCall($outputItem);
                        }
                        break;

                    case 'reasoning':
                        // Handle reasoning items (may be encrypted)
                        if (isset($outputItem['encrypted_content'])) {
                            // Encrypted reasoning for ZDR compliance
                            $reasoning[] = $outputItem;
                        } elseif (isset($outputItem['content'])) {
                            // Regular reasoning content
                            $reasoning[] = $outputItem;
                        }
                        break;

                    case 'function_call':
                    case 'function_call_output':
                        // Future: Handle function calls if needed
                        break;
                }
            }
        }

        $contentText = implode('', $texts);

        $result = [
            'content' => [
                'text' => $contentText,
                'groundingMetadata' => $this->formatGroundingMetadata($citations),
            ],
            'usage' => $this->extractUsage($jsonContent),
        ];

        // Add auxiliaries only if we have reasoning data
        if (! empty($reasoning)) {
            $result['auxiliaries'] = [
                [
                    'type' => 'openAiResponsesSpecific',
                    'content' => json_encode(['reasoning' => $reasoning]),
                ],
            ];
        }

        return $result;
    }

    /**
     * Format a single chunk from a streaming Responses API stream
     */
    public function formatStreamChunk(string $chunk): array
    {
        $jsonChunk = json_decode($chunk, true);
        $content = '';
        $isDone = false;
        $usage = null;
        $reasoning = [];
        $citations = [];

        if (empty($jsonChunk) || ! is_array($jsonChunk)) {
            return [
                'content' => ['text' => ''],
                'isDone' => false,
                'usage' => null,
            ];
        }

        // Handle different event types from Responses API streaming
        $eventType = $jsonChunk['type'] ?? '';

        switch ($eventType) {
            case 'response.output_text.delta':
                // Text content delta
                $content = $jsonChunk['delta'] ?? '';
                // Accumulate text for citation processing
                $this->fullMessageText .= $content;
                break;

            case 'response.output_text.annotation.added':
                // Handle annotation events (citations)
                if (isset($jsonChunk['annotation']) && $jsonChunk['annotation']['type'] === 'url_citation') {
                    $this->parseAnnotation($jsonChunk['annotation']);
                }
                break;

            case 'response.output_text.done':
                // Handle completion of text output - get full text
                if (isset($jsonChunk['text'])) {
                    $this->fullMessageText = $jsonChunk['text']; // Use complete text from done event
                }
                break;

            case 'response.content_part.done':
                // Handle completion of content part - get full text with annotations
                if (isset($jsonChunk['part']['text'])) {
                    $this->fullMessageText = $jsonChunk['part']['text']; // Use complete text from content part
                }
                
                // Process annotations from content part
                if (isset($jsonChunk['part']['annotations'])) {
                    Log::info('[OpenAI Responses] Processing annotations from content part', [
                        'count' => count($jsonChunk['part']['annotations'])
                    ]);
                    
                    foreach ($jsonChunk['part']['annotations'] as $annotation) {
                        if ($annotation['type'] === 'url_citation') {
                            $this->parseAnnotation($annotation);
                        }
                    }
                }
                break;

            case 'response.message.delta':
                // Message delta with content
                if (isset($jsonChunk['delta']['content'])) {
                    foreach ($jsonChunk['delta']['content'] as $contentItem) {
                        if ($contentItem['type'] === 'output_text') {
                            $content = $contentItem['text'] ?? '';

                            // Extract citations from annotations in streaming
                            if (isset($contentItem['annotations'])) {
                                foreach ($contentItem['annotations'] as $annotation) {
                                    if ($annotation['type'] === 'url_citation') {
                                        $citations[] = $annotation;
                                    }
                                }
                            }
                        }
                    }
                }
                break;

            case 'response.output_item.added':
                // Handle web search calls being added
                if (isset($jsonChunk['item']['type']) && $jsonChunk['item']['type'] === 'web_search_call') {
                    // Web search call started - no content yet
                }
                break;

            case 'response.output_item.done':
                // Handle completed web search calls
                if (isset($jsonChunk['item']['type']) && $jsonChunk['item']['type'] === 'web_search_call') {
                    $this->parseWebSearchCall($jsonChunk['item']);
                }
                
                // Handle completed message items with annotations
                if (isset($jsonChunk['item']['type']) && $jsonChunk['item']['type'] === 'message') {
                    if (isset($jsonChunk['item']['content'])) {
                        foreach ($jsonChunk['item']['content'] as $contentItem) {
                            if ($contentItem['type'] === 'output_text' && isset($contentItem['annotations'])) {
                                Log::info('[OpenAI Responses] Processing annotations from message item', [
                                    'count' => count($contentItem['annotations'])
                                ]);
                                
                                foreach ($contentItem['annotations'] as $annotation) {
                                    if ($annotation['type'] === 'url_citation') {
                                        $this->parseAnnotation($annotation);
                                    }
                                }
                            }
                        }
                    }
                }
                break;

            case 'response.completed':
            case 'response.refreshed':
                // Response completion signal
                $isDone = true;

                // Extract reasoning tokens from completed response
                if (isset($jsonChunk['response']['output'])) {
                    foreach ($jsonChunk['response']['output'] as $item) {
                        if ($item['type'] === 'reasoning') {
                            if (isset($item['encrypted_content'])) {
                                $reasoning[] = $item;
                            } elseif (isset($item['content'])) {
                                $reasoning[] = $item;
                            }
                        }
                    }
                }
                break;

            case 'response.function_call.delta':
            case 'response.function_call_output.delta':
                // Function calling deltas - could be implemented in future
                break;

            case 'error':
                // Error event
                $content = 'Error: '.($jsonChunk['error']['message'] ?? 'Unknown error');
                $isDone = true;
                break;
        }

        // Extract usage data if available
        if (! empty($jsonChunk['usage'])) {
            $usage = $this->extractUsage($jsonChunk);
        } elseif (! empty($jsonChunk['response']['usage'])) {
            $usage = $this->extractUsage($jsonChunk['response']);
        }

        $response = [
            'content' => [
                'text' => $content,
                'groundingMetadata' => ! empty($citations) ? $this->formatGroundingMetadata($citations) : null,
            ],
            'isDone' => $isDone,
            'usage' => $usage,
        ];

        // Add reasoning auxiliaries if present
        if (! empty($reasoning)) {
            $response['auxiliaries'] = [
                [
                    'type' => 'openAiResponsesSpecific',
                    'content' => json_encode(['reasoning' => $reasoning]),
                ],
            ];
        }

        // Add unified citations if we have search data and are done processing
        if ($isDone && (! empty($this->webSearchSources) || ! empty($this->searchQueries) || ! empty($this->annotations))) {
            Log::info('[OpenAI Responses] Formatting citations for completed message', [
                'webSearchSources_count' => count($this->webSearchSources),
                'searchQueries_count' => count($this->searchQueries),
                'annotations_count' => count($this->annotations),
                'fullMessageText_length' => strlen($this->fullMessageText)
            ]);
            
            $citationData = [
                'webSearchSources' => $this->webSearchSources,
                'searchQueries' => $this->searchQueries,
                'citations' => $this->annotations, // Google-compatible citations with start/end indices
            ];

            // Use accumulated message text for citation processing
            $messageText = $this->fullMessageText;

            $formattedCitations = $this->citationService->formatCitations('openai_responses', $citationData, $messageText);

            Log::info('[OpenAI Responses] CitationService result', [
                'formattedCitations' => $formattedCitations
            ]);

            if (! empty($formattedCitations)) {
                $response['content']['groundingMetadata'] = $formattedCitations;
                Log::info('[OpenAI Responses] Citations added to response content');
            }
        }

        return $response;
    }

    /**
     * Format a single chunk into ready-to-send messages for the frontend
     */
    public function formatStreamMessages(string $chunk, array $messageContext): array
    {
        $chunkData = $this->formatStreamChunk($chunk);

        // Always return a message for done status, even if no text
        // For non-done chunks, only return if there's actual text content
        if (empty($chunkData['content']['text']) && ! $chunkData['isDone']) {
            return [];
        }

        return [[
            'author' => $messageContext['author'],
            'model' => $messageContext['model'],
            'isDone' => $chunkData['isDone'],
            'content' => json_encode($chunkData['content']), // Include ALL content (text + citations)
            'usage' => $chunkData['usage'] ?? null,
            'auxiliaries' => $chunkData['auxiliaries'] ?? [],
        ]];
    }

    /**
     * Establish a connection to the OpenAI Responses API
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function connect(array $payload, ?callable $streamCallback = null)
    {
        // Reset search data for new request
        $this->resetSearchData();

        $modelId = $payload['model'] ?? '';

        // Validate model compatibility with Responses API
        if (! $this->isModelCompatible($modelId)) {
            throw new \Exception("Model '{$modelId}' is not compatible with OpenAI Responses API. Only gpt-4 and later model families are supported.");
        }

        if ($streamCallback) {
            return $this->makeStreamingRequest($payload, $streamCallback);
        } else {
            return $this->makeNonStreamingRequest($payload);
        }
    }

    /**
     * Check if a model is compatible with the OpenAI Responses API
     * Only gpt-4 and later model families are supported
     */
    public function isModelCompatible(string $modelId): bool
    {
        // Responses API is only available for gpt-4 and later model families
        $compatiblePrefixes = [
            'gpt-4',      // GPT-4 family
            'gpt-5',      // GPT-5 family (future)
            'gpt-6',      // GPT-6 family (future)
            // Note: o1 and o3 models are not supported by Responses API
        ];

        foreach ($compatiblePrefixes as $prefix) {
            if (strpos($modelId, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a model supports Web Search functionality
     */
    public function modelSupportsSearch(string $modelId): bool
    {
        // Primary: Check database model settings for web_search capability
        try {
            $modelDetails = $this->getModelDetails($modelId);

            if (is_array($modelDetails)) {
                // Check if web_search is in the settings array
                if (isset($modelDetails['settings'])) {
                    $settings = is_array($modelDetails['settings'])
                        ? $modelDetails['settings']
                        : json_decode($modelDetails['settings'], true);

                    // Settings should be a direct array, not nested
                    if (isset($settings['web_search']) && $settings['web_search'] === true) {
                        return true;
                    }
                }

                // Check if web_search is in the top level (from information field)
                if (isset($modelDetails['web_search'])) {
                    return (bool) $modelDetails['web_search'];
                }
            }
        } catch (\Exception $e) {
            // If database check fails, fall back to model name analysis
            Log::warning('Failed to get model details for web search check: '.$e->getMessage());
        }

        // Fallback: Based on OpenAI documentation, web search is available for:
        // gpt-4o-mini, gpt-4o, gpt-4.1-mini, gpt-4.1, o4-mini, o3, gpt-5
        $modelIdLower = strtolower($modelId);

        return strpos($modelIdLower, 'gpt-4o') === 0 ||
               strpos($modelIdLower, 'gpt-4.1') === 0 ||
               strpos($modelIdLower, 'o4-mini') === 0 ||
               strpos($modelIdLower, 'o3') === 0 ||
               strpos($modelIdLower, 'gpt-5') === 0;
    }

    /**
     * Check if a model supports streaming
     */
    public function supportsStreaming(string $modelId): bool
    {
        // First check if model is compatible with Responses API
        if (! $this->isModelCompatible($modelId)) {
            return false;
        }

        $modelDetails = $this->getModelDetails($modelId);

        return $modelDetails['streamable'] ?? true;
    }

    /**
     * Fetch available models from the provider's API
     * Only returns models compatible with the Responses API (gpt-4 and later)
     *
     * @throws \Exception
     */
    public function fetchAvailableModelsFromAPI(): array
    {
        try {
            $url = $this->getModelsEndpointUrl();

            // Build headers for Laravel HTTP client (associative array format)
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];

            // Add authorization header if API key is present
            if (! empty($this->config['api_key'])) {
                $headers['Authorization'] = 'Bearer '.$this->config['api_key'];
            }

            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->get($url);

            if (! $response->successful()) {
                throw new \Exception('API request failed: '.$response->status());
            }

            $apiResponse = $response->json();

            // Filter models to only include those compatible with Responses API
            if (isset($apiResponse['data']) && is_array($apiResponse['data'])) {
                $apiResponse['data'] = array_filter($apiResponse['data'], function ($model) {
                    return $this->isModelCompatible($model['id'] ?? '');
                });

                // Re-index the array to avoid gaps
                $apiResponse['data'] = array_values($apiResponse['data']);
            }

            return $apiResponse;

        } catch (\Exception $e) {
            Log::error('OpenAI Responses API models fetch failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract usage information from Responses API output
     */
    protected function extractUsage(array $data): ?array
    {
        // Responses API usage may appear under top-level 'usage' or nested structures.
        if (empty($data)) {
            return null;
        }

        if (! empty($data['usage']) && is_array($data['usage'])) {
            $usage = $data['usage'];

            return [
                'prompt_tokens' => $usage['prompt_tokens'] ?? ($usage['input_tokens'] ?? null),
                'completion_tokens' => $usage['completion_tokens'] ?? ($usage['output_tokens'] ?? null),
            ];
        }

        // Fallback: some events may include usage-like fields under metadata
        if (! empty($data['metadata']) && ! empty($data['metadata']['usage'])) {
            $usage = $data['metadata']['usage'];

            return [
                'prompt_tokens' => $usage['prompt_tokens'] ?? null,
                'completion_tokens' => $usage['completion_tokens'] ?? null,
            ];
        }

        return null;
    }

    /**
     * Parse and store web search sources from OpenAI Responses API web_search_call output
     */
    protected function parseWebSearchCall(array $webSearchCall): void
    {
        // OpenAI web_search_call includes action information
        if (isset($webSearchCall['action'])) {
            $action = $webSearchCall['action'];

            // Store query information if available
            if (isset($action['query'])) {
                $this->searchQueries[] = $action['query'];
                Log::info('[OpenAI Responses] Web search query: '.$action['query']);
            }

            // Extract sources from the action
            if (isset($action['sources']) && is_array($action['sources'])) {
                foreach ($action['sources'] as $source) {
                    if (isset($source['url'], $source['title'])) {
                        $this->webSearchSources[] = [
                            'url' => $source['url'],
                            'title' => $source['title'],
                            'snippet' => $source['snippet'] ?? null,
                        ];
                    }
                }
            }
        }

        // Log for debugging - check if we're getting sources
        Log::info('[OpenAI Responses] Web search sources collected: '.count($this->webSearchSources));
    }

    /**
     * Parse annotation events from streaming (citations)
     */
    protected function parseAnnotation(array $annotation): void
    {
        if ($annotation['type'] === 'url_citation') {
            // Store annotation with Google-compatible format
            $this->annotations[] = [
                'start_index' => $annotation['start_index'] ?? null,
                'end_index' => $annotation['end_index'] ?? null,
                'url' => $annotation['url'] ?? null,
                'title' => $annotation['title'] ?? null,
            ];

            // Also add to webSearchSources for unified source listing
            $url = $annotation['url'] ?? null;
            $title = $annotation['title'] ?? null;
            
            if ($url && $title) {
                // Check if this source is already in webSearchSources (avoid duplicates)
                $sourceExists = false;
                foreach ($this->webSearchSources as $existingSource) {
                    if ($existingSource['url'] === $url) {
                        $sourceExists = true;
                        break;
                    }
                }
                
                if (!$sourceExists) {
                    $this->webSearchSources[] = [
                        'url' => $url,
                        'title' => $title,
                        'snippet' => '', // OpenAI Responses annotations don't provide snippets
                    ];
                }
            }

            Log::info('[OpenAI Responses] Citation annotation added: '.$annotation['title']);
        }

        // Log for debugging
        Log::info('[OpenAI Responses] Total annotations collected: '.count($this->annotations));
        Log::info('[OpenAI Responses] Total web search sources: '.count($this->webSearchSources));
    }

    /**
     * Reset search data for new requests
     */
    protected function resetSearchData(): void
    {
        $this->webSearchSources = [];
        $this->searchQueries = [];
        $this->annotations = [];
        $this->fullMessageText = '';
    }

    /**
     * Format grounding metadata from citations and web search sources
     */
    protected function formatGroundingMetadata(?array $citations): ?array
    {
        if (empty($citations) && empty($this->webSearchSources)) {
            return null;
        }

        $groundingSupports = [];
        $webSearchQueries = [];

        // Process OpenAI url_citation annotations
        if (! empty($citations)) {
            foreach ($citations as $citation) {
                if (isset($citation['url'], $citation['title'])) {
                    $groundingSupports[] = [
                        'startIndex' => $citation['start_index'] ?? null,
                        'endIndex' => $citation['end_index'] ?? null,
                        'url' => $citation['url'],
                        'title' => $citation['title'],
                    ];
                }
            }
        }

        // Add sources from web search calls
        foreach ($this->webSearchSources as $source) {
            // Avoid duplicates by checking if URL already exists
            $exists = false;
            foreach ($groundingSupports as $support) {
                if ($support['url'] === $source['url']) {
                    $exists = true;
                    break;
                }
            }

            if (! $exists) {
                $groundingSupports[] = [
                    'startIndex' => null,
                    'endIndex' => null,
                    'url' => $source['url'],
                    'title' => $source['title'],
                ];
            }
        }

        if (empty($groundingSupports)) {
            return null;
        }

        return [
            'webSearchQueries' => $webSearchQueries,
            'groundingSupports' => $groundingSupports,
        ];
    }

    /**
     * Make a non-streaming request to the OpenAI Responses API
     *
     * @param  array  $payload  The formatted payload
     * @return mixed The response
     */
    public function makeNonStreamingRequest(array $payload)
    {
        // Ensure stream is set to false
        $payload['stream'] = false;

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getChatEndpointUrl());

        // Set common cURL options
        $this->setCommonCurlOptions($ch, $payload, $this->getHttpHeaders());

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
     * Make a streaming request to the OpenAI Responses API
     *
     * @param  array  $payload  The formatted payload
     * @param  callable  $streamCallback  Callback for streaming responses
     * @return void
     */
    public function makeStreamingRequest(array $payload, callable $streamCallback)
    {
        // Ensure stream is set to true
        $payload['stream'] = true;

        set_time_limit(120);

        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getChatEndpointUrl());

        // Set common cURL options
        $this->setCommonCurlOptions($ch, $payload, $this->getHttpHeaders(true));

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
}
