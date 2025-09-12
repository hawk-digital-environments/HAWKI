<?php

namespace App\Services\AI\Providers;

use App\Services\Citations\CitationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicProvider extends BaseAIModelProvider
{
    use WebSearchTrait;

    /**
     * Citation service for unified citation formatting
     */
    private CitationService $citationService;

    /**
     * Accumulated web search citations during stream processing
     */
    private array $webSearchCitations = [];

    /**
     * Accumulated web search sources (from web_search_tool_result)
     */
    private array $webSearchSources = [];

    /**
     * Track current text block and associated citations
     */
    private int $currentBlockIndex = -1;

    private array $blockCitations = [];

    private bool $inTextBlock = false;

    /**
     * Constructor for AnthropicProvider
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->providerId = 'Anthropic'; // Must match database (case-sensitive)
        $this->citationService = app(CitationService::class);
    }

    /**
     * Get HTTP headers for models API requests
     * Anthropic uses x-api-key header instead of Authorization Bearer
     */
    protected function getModelsApiHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if (! empty($this->config['api_key'])) {
            $headers['x-api-key'] = $this->config['api_key'];
            $headers['anthropic-version'] = '2023-06-01'; // Required by Anthropic API
        }

        return $headers;
    }

    /**
     * Format the raw payload for Anthropic API
     */
    public function formatPayload(array $rawPayload): array
    {
        $messages = $rawPayload['messages'];
        $modelId = $rawPayload['model'];

        // Extract system prompt from first message item
        $systemPrompt = null;
        if (isset($messages[0]) && $messages[0]['role'] === 'system') {
            $systemPrompt = $messages[0]['content']['text'] ?? '';
            array_shift($messages);
        }

        // Format messages for Anthropic
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'role' => $message['role'],
                'content' => $message['content']['text'],
            ];
        }

        // Build payload
        $payload = [
            'model' => $modelId,
            'messages' => $formattedMessages,
            'max_tokens' => $rawPayload['max_tokens'] ?? 4096,
            'stream' => $rawPayload['stream'] && $this->supportsStreaming($modelId),
        ];

        // Add system prompt if present
        if ($systemPrompt) {
            $payload['system'] = $systemPrompt;
        }

        // Add optional parameters
        if (isset($rawPayload['temperature'])) {
            $payload['temperature'] = $rawPayload['temperature'];
        }

        if (isset($rawPayload['top_p'])) {
            $payload['top_p'] = $rawPayload['top_p'];
        }

        // Add Web Search Tool if enabled
        $additionalSettings = $this->config['additional_settings'] ?? [];
        // Check if model supports search based on database information
        $this->addWebSearchTools($payload, $modelId, $rawPayload);

        return $payload;
    }

    /**
     * Format the complete response from Anthropic
     *
     * @param  mixed  $response
     */
    public function formatResponse($response): array
    {
        $data = json_decode($response, true);

        if (! $data || ! isset($data['content'])) {
            return [
                'content' => [
                    'text' => '',
                    'groundingMetadata' => null,
                ],
                'usage' => null,
            ];
        }

        $content = '';
        $groundingMetadata = null;

        if (isset($data['content'][0]['text'])) {
            $content = $data['content'][0]['text'];
        }

        // Check for web search results in the response
        foreach ($data['content'] as $contentBlock) {
            if (isset($contentBlock['type']) && $contentBlock['type'] === 'web_search_tool_result') {
                // Reset sources and parse the web search results
                $this->webSearchSources = [];
                if (isset($contentBlock['content'])) {
                    $this->parseWebSearchSources($contentBlock['content']);
                }

                // Note: Citations will be formatted later when final message text is available
            }
        }

        $usage = null;
        if (isset($data['usage'])) {
            $usage = [
                'prompt_tokens' => $data['usage']['input_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['output_tokens'] ?? 0,
                'total_tokens' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            ];
        }

        return [
            'content' => [
                'text' => $content,
                'groundingMetadata' => $groundingMetadata,
            ],
            'usage' => $usage,
        ];
    }

    /**
     * Format a single chunk from Anthropic streaming response
     */
    public function formatStreamChunk(string $chunk): array
    {
        $content = '';
        $isDone = false;
        $usage = null;
        $groundingMetadata = null;

        // First try to parse as direct JSON (most common case)
        $data = json_decode($chunk, true);
        if ($data && isset($data['type'])) {
            $result = $this->processAnthropicEvent($data);
            $content = $result['content'];
            $isDone = $result['isDone'];
            $usage = $result['usage'];
            $groundingMetadata = $result['groundingMetadata'] ?? null;
        } else {
            // Fallback: Try SSE format parsing (for chunks with multiple events)
            $lines = explode('
', $chunk);

            foreach ($lines as $line) {
                $line = trim($line);

                if (strpos($line, 'data: ') === 0) {
                    $jsonData = substr($line, 6);

                    if (empty($jsonData) || $jsonData === '{"type": "ping"}') {
                        continue;
                    }

                    $data = json_decode($jsonData, true);
                    if ($data && isset($data['type'])) {
                        $result = $this->processAnthropicEvent($data);
                        $content .= $result['content']; // Accumulate content from multiple events
                        if ($result['isDone']) {
                            $isDone = true;
                        }
                        if ($result['usage']) {
                            $usage = $result['usage'];
                        }
                        if (isset($result['groundingMetadata']) && $result['groundingMetadata']) {
                            $groundingMetadata = $result['groundingMetadata'];
                        }
                    }
                }
            }
        }

        // Format final citations if we have sources and the message is complete
        if ($isDone && ! empty($this->webSearchSources)) {
            Log::debug('AnthropicProvider: Formatting citations for completed message', [
                'sources_count' => count($this->webSearchSources),
                'citations_count' => count($this->webSearchCitations),
                'message_length' => strlen($content),
            ]);
            $groundingMetadata = $this->formatCitations($content);
            Log::debug('AnthropicProvider: Citations formatted', [
                'has_metadata' => ! is_null($groundingMetadata),
            ]);
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
     * Process a single Anthropic event (either from direct JSON or SSE)
     */
    private function processAnthropicEvent(array $data): array
    {
        $content = '';
        $isDone = false;
        $usage = null;
        $groundingMetadata = null;

        switch ($data['type']) {
            case 'message_start':
                // Message has started, reset citation cache
                $this->webSearchCitations = [];
                $this->webSearchSources = [];
                $this->currentBlockIndex = -1;
                $this->blockCitations = [];
                $this->inTextBlock = false;
                break;

            case 'content_block_start':
                // Content block has started
                $this->currentBlockIndex = $data['index'] ?? $this->currentBlockIndex + 1;

                if (isset($data['content_block']['type'])) {
                    switch ($data['content_block']['type']) {
                        case 'web_search_tool_result':
                            // Web search sources block (Index 2) - parse and store search sources for Citations
                            // Reset sources to avoid duplicates from multiple tool results
                            $this->webSearchSources = [];
                            if (isset($data['content_block']['content'])) {
                                $this->parseWebSearchSources($data['content_block']['content']);
                            }
                            break;

                        case 'server_tool_use':
                            // Server tool use (like web search) starting - no visible content
                            // Log::debug('AnthropicProvider: Server tool use started (UPDATED CODE)', [
                            //    'tool_name' => $data['content_block']['name'] ?? 'unknown',
                            //    'tool_id' => $data['content_block']['id'] ?? 'unknown'
                            // ]);
                            break;

                        case 'text':
                            // Regular text block starting - check if it has citations
                            $this->inTextBlock = true;
                            $this->blockCitations[$this->currentBlockIndex] = [];

                            // Check if this block has citations attached
                            if (isset($data['content_block']['citations']) && is_array($data['content_block']['citations'])) {
                                foreach ($data['content_block']['citations'] as $citation) {
                                    $this->blockCitations[$this->currentBlockIndex][] = $citation;
                                }
                            }
                            break;

                        default:
                            Log::debug('AnthropicProvider unknown content block type:', ['type' => $data['content_block']['type']]);
                            break;
                    }
                }
                break;

            case 'content_block_stop':
                // Content block has ended
                if ($this->inTextBlock) {
                    $this->inTextBlock = false;

                    // Add inline citations if we have any for this block
                    if (isset($this->blockCitations[$this->currentBlockIndex]) &&
                        ! empty($this->blockCitations[$this->currentBlockIndex])) {

                        // Generate the citation numbers for this block as separate content
                        $content = $this->addInlineCitations('');
                    }
                }
                break;

            case 'content_block_delta':
                // Handle different types of content block deltas
                if (isset($data['delta']['type'])) {
                    switch ($data['delta']['type']) {
                        case 'text_delta':
                            // Regular text content
                            if (isset($data['delta']['text'])) {
                                $content = $data['delta']['text'];

                                // Don't add inline citations here - they will be added at content_block_stop

                                // Note: Citations will be formatted later when final message text is available
                            }
                            break;

                        case 'citations_delta':
                            // Web search citations - parse and store with text position
                            if (isset($data['delta']['citation'])) {
                                // Single citation in this delta
                                $this->parseCitationDelta($data['delta']['citation']);
                            } elseif (isset($data['delta']['citations']) && is_array($data['delta']['citations'])) {
                                // Multiple citations in this delta (fallback)
                                foreach ($data['delta']['citations'] as $citation) {
                                    $this->parseCitationDelta($citation);
                                }
                            }
                            break;

                        case 'input_json_delta':
                            // Web Search tool input being streamed - no visible content but log for debugging
                            // Log::debug('AnthropicProvider: Web search tool input', [
                            //    'partial_json' => $data['delta']['partial_json'] ?? ''
                            // ]);
                            break;

                        default:
                            Log::debug('AnthropicProvider unknown delta type:', ['type' => $data['delta']['type'], 'delta' => $data['delta']]);
                            break;
                    }
                }
                break;

            case 'content_block_stop':
                // Content block has ended
                break;

            case 'server_tool_use':
            case 'web_search_tool_result':
                // These events are passed through to processAnthropicEvent as data objects
                // No special handling needed here
                break;

            case 'message_delta':
                // Message metadata update, may contain usage info
                if (isset($data['usage'])) {
                    $usage = [
                        'prompt_tokens' => $data['usage']['input_tokens'] ?? 0,
                        'completion_tokens' => $data['usage']['output_tokens'] ?? 0,
                        'total_tokens' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
                    ];
                }
                break;

            case 'message_stop':
                // Message has completely finished
                $isDone = true;

                // Note: Final citations will be formatted in formatStreamChunk when complete text is available
                break;

            case 'ping':
                // Keep-alive ping, ignore
                break;

            default:
                // Unknown event type, log for debugging
                Log::debug("Unknown Anthropic stream event type: {$data['type']}", ['data' => $data]);
                break;
        }

        return [
            'content' => $content,
            'isDone' => $isDone,
            'usage' => $usage,
            'groundingMetadata' => $groundingMetadata,
        ];
    }

    /**
     * Parse and store web search sources from Anthropic's web_search_tool_result content blocks
     * These become the "Search Sources" in the Citation UI
     */
    private function parseWebSearchSources(array $searchContent): void
    {
        Log::debug('AnthropicProvider: Parsing web search sources', [
            'incoming_results_count' => count($searchContent),
            'current_sources_count' => count($this->webSearchSources),
        ]);

        foreach ($searchContent as $result) {
            if (isset($result['type']) && $result['type'] === 'web_search_result') {
                $source = [
                    'title' => $result['title'] ?? '',
                    'url' => $result['url'] ?? '',
                    'content' => $result['encrypted_content'] ?? $result['content'] ?? '',
                    'page_age' => $result['page_age'] ?? null,
                ];
                $this->webSearchSources[] = $source;
            }
        }

        Log::debug('AnthropicProvider: Web search sources parsed', [
            'total_sources_count' => count($this->webSearchSources),
        ]);
    }

    /**
     * Parse and store citations from Anthropic's citations_delta events
     * These are the actual citations with cited_text that reference the search sources
     */
    private function parseCitationDelta(array $citation): void
    {
        // Anthropic citations from citations_delta have type web_search_result_location
        if (isset($citation['type']) && $citation['type'] === 'web_search_result_location') {
            $title = $citation['title'] ?? '';
            $url = $citation['url'] ?? '';
            $citedText = $citation['cited_text'] ?? '';

            $citationData = [
                'title' => $title,
                'url' => $url,
                'content' => $citedText,  // This is the actual cited text
                'cited_text' => $citedText,  // Keep both for compatibility
            ];

            // Check if this citation already exists (avoid duplicates by URL + cited_text)
            $exists = false;
            foreach ($this->webSearchCitations as $existingCitation) {
                if ($existingCitation['url'] === $citationData['url'] &&
                    $existingCitation['cited_text'] === $citationData['cited_text']) {
                    $exists = true;
                    break;
                }
            }

            if (! $exists) {
                $this->webSearchCitations[] = $citationData;

                // Add to current block citations if we're in a text block
                if ($this->inTextBlock && $this->currentBlockIndex >= 0) {
                    if (! isset($this->blockCitations[$this->currentBlockIndex])) {
                        $this->blockCitations[$this->currentBlockIndex] = [];
                    }
                    $this->blockCitations[$this->currentBlockIndex][] = $citationData;
                }
            }
        }
    }

    /**
     * Format citations using the unified Citation Service
     *
     * @param  string  $messageText  The current message text with inline citations
     * @return array|null Formatted citation data or null if no citations
     */
    private function formatCitations(string $messageText): ?array
    {
        // We need both search sources AND citations for proper formatting
        if (empty($this->webSearchSources) && empty($this->webSearchCitations)) {
            Log::debug('AnthropicProvider: No search sources or citations to format');

            return null;
        }

        Log::debug('AnthropicProvider: Preparing citation data', [
            'sources_count' => count($this->webSearchSources),
            'citations_count' => count($this->webSearchCitations),
            'sources' => $this->webSearchSources,
            'citations' => $this->webSearchCitations,
        ]);

        // For Anthropic, we use the search sources as the primary data
        // The citations (web_search_result_location) contain the cited_text but reference the same URLs
        $providerData = [
            'groundingChunks' => [],
        ];

        // Use search sources as the primary data for Citation Service
        foreach ($this->webSearchSources as $source) {
            $providerData['groundingChunks'][] = [
                'web' => [
                    'title' => $source['title'],
                    'uri' => $source['url'],
                ],
            ];
        }

        Log::debug('AnthropicProvider: Calling CitationService', [
            'provider_data' => $providerData,
        ]);

        // Use the unified citation service
        $result = $this->citationService->formatCitations('anthropic', $providerData, $messageText);

        Log::debug('AnthropicProvider: CitationService result', [
            'has_result' => ! is_null($result),
            'result' => $result,
        ]);

        return $result;
    }

    /**
     * Get Anthropic-specific web search tool configuration
     */
    public function getWebSearchToolConfig(): array
    {
        return [
            [
                'type' => 'web_search_20250305',
                'name' => 'web_search',
                'max_uses' => 5,
            ],
        ];
    }

    /**
     * Make a non-streaming request to Anthropic
     *
     * @return mixed
     */
    public function makeNonStreamingRequest(array $payload)
    {
        $provider = $this->getProviderFromDatabase();
        $chatUrl = $provider ? $provider->chat_url : '';

        if (! $chatUrl) {
            throw new \Exception('No chat URL configured for Anthropic provider');
        }

        $headers = $this->getModelsApiHeaders();

        $response = Http::withHeaders($headers)->post($chatUrl, $payload);

        if (! $response->successful()) {
            throw new \Exception("Anthropic API request failed: HTTP {$response->status()}");
        }

        return $response->body();
    }

    /**
     * Make a streaming request to Anthropic
     *
     * @return void
     */
    public function makeStreamingRequest(array $payload, callable $streamCallback)
    {
        $provider = $this->getProviderFromDatabase();
        $chatUrl = $provider ? $provider->chat_url : '';

        if (! $chatUrl) {
            throw new \Exception('No chat URL configured for Anthropic provider');
        }

        // Convert associative array headers to cURL format
        $headers = [];
        $apiHeaders = $this->getModelsApiHeaders();
        foreach ($apiHeaders as $key => $value) {
            $headers[] = "{$key}: {$value}";
        }

        // Initialize cURL for streaming
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $chatUrl);

        // Set common cURL options
        $this->setCommonCurlOptions($ch, $payload, $headers);

        // Set streaming-specific options
        $this->setStreamingCurlOptions($ch, $streamCallback);

        // Execute the cURL session
        curl_exec($ch);

        if (curl_errno($ch)) {
            $error = 'Error: '.curl_error($ch);
            curl_close($ch);
            throw new \Exception($error);
        }

        curl_close($ch);
    }

    /**
     * Add inline citations to text content
     *
     * @param  string  $content  The text content to add citations to
     * @return string The content with inline citation numbers
     */
    private function addInlineCitations(string $content): string
    {
        if (empty($this->blockCitations[$this->currentBlockIndex])) {
            return $content;
        }

        // Get citations for this block and add them as footnotes
        $citations = $this->blockCitations[$this->currentBlockIndex];
        $citationNumbers = [];

        foreach ($citations as $citation) {
            // Find the citation number based on its position in webSearchSources (not webSearchCitations)
            // because webSearchSources are the actual sources that become numbered in the UI
            foreach ($this->webSearchSources as $index => $webSource) {
                if ($webSource['url'] === $citation['url']) {
                    $citationNumbers[] = $index + 1; // 1-based indexing
                    break;
                }
            }
        }

        // Add citation numbers - if content is empty, just return the citations
        if (! empty($citationNumbers)) {
            $footnotes = implode(',', array_unique($citationNumbers));
            if (empty($content)) {
                // This is called from content_block_stop, just return the citation markers
                $result = " [{$footnotes}]";
            } else {
                // This would be called from text_delta (currently disabled)
                $result = $content." [{$footnotes}]";
            }

            return $result;
        }

        return $content;
    }
}
