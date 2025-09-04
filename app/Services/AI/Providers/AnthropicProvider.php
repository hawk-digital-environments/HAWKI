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
        Log::info('AnthropicProvider formatStreamChunk input:', ['chunk' => $chunk]);

        $content = '';
        $isDone = false;
        $usage = null;

        // Try to parse as direct JSON first (which is what we're actually receiving)
        $data = json_decode($chunk, true);
        if ($data && isset($data['type'])) {
            Log::info('AnthropicProvider processing direct JSON event:', ['type' => $data['type'], 'data' => $data]);

            switch ($data['type']) {
                case 'message_start':
                    // Message has started, no content yet
                    break;

                case 'content_block_start':
                    // Content block has started, no content yet
                    break;

                case 'content_block_delta':
                    // This contains the actual text content
                    if (isset($data['delta']['type']) && $data['delta']['type'] === 'text_delta') {
                        if (isset($data['delta']['text'])) {
                            $extractedText = $data['delta']['text'];
                            $content .= $extractedText;
                            Log::info('AnthropicProvider extracted text:', ['text' => $extractedText, 'totalContent' => $content]);
                        }
                    }
                    break;

                case 'content_block_stop':
                    // Content block has ended
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
        } else {
            // Fallback: Try SSE format parsing for legacy compatibility
            $lines = explode("\n", $chunk);

            foreach ($lines as $line) {
                $line = trim($line);

                if (strpos($line, 'data: ') === 0) {
                    $jsonData = substr($line, 6);

                    if (empty($jsonData) || $jsonData === '{"type": "ping"}') {
                        continue;
                    }

                    $data = json_decode($jsonData, true);
                    if ($data && isset($data['type']) && $data['type'] === 'content_block_delta') {
                        if (isset($data['delta']['type']) && $data['delta']['type'] === 'text_delta' && isset($data['delta']['text'])) {
                            $content .= $data['delta']['text'];
                        }
                    } elseif ($data && isset($data['type']) && $data['type'] === 'message_stop') {
                        $isDone = true;
                    }
                }
            }
        }

        $result = [
            'content' => [
                'text' => $content,
            ],
            'isDone' => $isDone,
            'usage' => $usage,
        ];
        Log::info('AnthropicProvider formatStreamChunk result:', $result);

        return $result;
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
}
