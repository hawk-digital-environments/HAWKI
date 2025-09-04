<?php

namespace App\Services\AI\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicProvider extends BaseAIModelProvider
{
    /**
     * Constructor for AnthropicProvider
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->providerId = 'Anthropic'; // Must match database (case-sensitive)
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
        $supportsSearch = $this->modelSupportsSearch($modelId);

        if ($supportsSearch) {
            $payload['tools'] = $rawPayload['tools'] ?? [
                [
                    'type' => 'web_search_20250305',
                    'name' => 'web_search',
                    'max_uses' => 5,
                ],
            ];
        }

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
            return ['content' => '', 'usage' => null];
        }

        $content = '';
        if (isset($data['content'][0]['text'])) {
            $content = $data['content'][0]['text'];
        }

        $usage = null;
        if (isset($data['usage'])) {
            $usage = [
                'prompt_tokens' => $data['usage']['input_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['output_tokens'] ?? 0,
                'total_tokens' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            ];
        }

        return ['content' => $content, 'usage' => $usage];
    }

    /**
     * Format a single chunk from Anthropic streaming response
     */
    public function formatStreamChunk(string $chunk): array
    {
        $content = '';
        $isDone = false;
        $usage = null;

        // First try to parse as direct JSON (most common case)
        $data = json_decode($chunk, true);
        if ($data && isset($data['type'])) {
            $result = $this->processAnthropicEvent($data);
            $content = $result['content'];
            $isDone = $result['isDone'];
            $usage = $result['usage'];
        } else {
            // Fallback: Try SSE format parsing (for chunks with multiple events)
            $lines = explode("
", $chunk);

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
                        if ($result['isDone']) $isDone = true;
                        if ($result['usage']) $usage = $result['usage'];
                    }
                }
            }
        }

        return [
            'content' => [
                'text' => $content,
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

        switch ($data['type']) {
            case 'message_start':
                // Message has started, no content yet
                break;

            case 'content_block_start':
                // Content block has started
                if (isset($data['content_block']['type'])) {
                    switch ($data['content_block']['type']) {
                        case 'web_search_tool_result':
                            // Web search results block - return empty content (not user-visible)
                            break;
                            
                        case 'text':
                            // Regular text block starting
                            break;
                            
                        default:
                            Log::debug('AnthropicProvider unknown content block type:', ['type' => $data['content_block']['type']]);
                            break;
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
                            }
                            break;
                            
                        case 'input_json_delta':
                            // Web Search tool input - return empty content (not user-visible)
                            break;
                            
                        case 'citations_delta':
                            // Citations for web search results - return empty content (not user-visible)
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
                // Server tool use (like web search) - return empty content
                break;

            case 'web_search_tool_result':
                // Web search results - return empty content  
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
                break;

            case 'ping':
                // Keep-alive ping, ignore
                break;

            default:
                // Unknown event type, log for debugging
                Log::debug("Unknown Anthropic stream event type: {$data['type']}", ['data' => $data]);
                break;
        }

        return ['content' => $content, 'isDone' => $isDone, 'usage' => $usage];
    }

    /**
     * Check if a model supports Web Search functionality
     */
    public function modelSupportsSearch(string $modelId): bool
    {
        // Primary: Check database model settings
        try {
            $modelDetails = $this->getModelDetails($modelId);
            
            if (is_array($modelDetails)) {
                // Check if search_tool is in the top level (from information field)
                if (isset($modelDetails['search_tool'])) {
                    return (bool) $modelDetails['search_tool'];
                }

                // Check if search_tool is in the settings sub-array
                if (isset($modelDetails['settings']['search_tool'])) {
                    return (bool) $modelDetails['settings']['search_tool'];
                }
            }
        } catch (\Exception $e) {
            // If database check fails, fall back to model name analysis
            Log::warning('Failed to get model details for search check: '.$e->getMessage());
        }

        // Fallback: Only Claude Opus 4+ models support web search
        $modelIdLower = strtolower($modelId);
        
        // Check if it's Claude Opus 4 or newer
        return strpos($modelIdLower, 'claude-opus-4') !== false ||
               strpos($modelIdLower, 'claude-sonnet-4') !== false ||
               strpos($modelIdLower, 'claude-sonnet-3.7') !== false ||
               strpos($modelIdLower, 'claude-3-5-sonnet') !== false ||
               strpos($modelIdLower, 'claude-3-5-haiku') !== false;
    }    /**
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
}
