<?php

namespace App\Services\AI\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\LanguageModel;
use App\Models\ProviderSetting;

class OpenAIProvider extends BaseAIModelProvider
{
    /**
     * Constructor for OpenAIProvider
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->providerId = 'openai'; // Explizites Setzen der providerId
    }
    
    /**
     * Get the chat endpoint URL from the provider's API format configuration
     *
     * @return string
     */
    protected function getChatEndpointUrl(): string
    {
        $provider = $this->getProviderFromDatabase();
        return $provider ? $provider->chat_url : $this->config['base_url'] ?? '';
    }
    
    /**
     * Get the models endpoint URL from the provider's API format configuration  
     *
     * @return string
     */
    protected function getModelsEndpointUrl(): string
    {
        $provider = $this->getProviderFromDatabase();
        return $provider ? $provider->ping_url : $this->config['ping_url'] ?? '';
    }
    
    /**
     * Get the provider settings from database
     *
     * @return ProviderSetting|null
     */
    protected function getProviderFromDatabase(): ?ProviderSetting
    {
        return ProviderSetting::where('provider_name', $this->providerId)
            ->where('is_active', true)
            ->first();
    }
    
    /**
     * Format the raw payload for OpenAI API
     *
     * @param array $rawPayload
     * @return array
     */
    public function formatPayload(array $rawPayload): array
    {
        $messages = $rawPayload['messages'];
        $modelId = $rawPayload['model'];
        
        // Handle special cases for specific models
        $messages = $this->handleModelSpecificFormatting($modelId, $messages);
        
        // Format messages for OpenAI
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'role' => $message['role'],
                'content' => $message['content']['text']
            ];
        }
        
        // Build payload with common parameters
        $payload = [
            'model' => $modelId,
            'messages' => $formattedMessages,
            'stream' => $rawPayload['stream'] && $this->supportsStreaming($modelId),
        ];
        
        // Add optional parameters if present in the raw payload
        if (isset($rawPayload['temperature'])) {
            $payload['temperature'] = $rawPayload['temperature'];
        }
        
        if (isset($rawPayload['top_p'])) {
            $payload['top_p'] = $rawPayload['top_p'];
        }
        
        if (isset($rawPayload['frequency_penalty'])) {
            $payload['frequency_penalty'] = $rawPayload['frequency_penalty'];
        }
        
        if (isset($rawPayload['presence_penalty'])) {
            $payload['presence_penalty'] = $rawPayload['presence_penalty'];
        }
        
        return $payload;
    }
    
    /**
     * Format the complete response from OpenAI
     *
     * @param mixed $response
     * @return array
     */
    public function formatResponse($response): array
    {
        $responseContent = $response->getContent();
        $jsonContent = json_decode($responseContent, true);
        
        $content = $jsonContent['choices'][0]['message']['content'] ?? '';
        
        return [
            'content' => [
                'text' => $content,
            ],
            'usage' => $this->extractUsage($jsonContent)
        ];
    }
    
    /**
     * Format a single chunk from a streaming response
     *
     * @param string $chunk
     * @return array
     */
    public function formatStreamChunk(string $chunk): array
    {
        $content = '';
        $isDone = false;
        $usage = null;

        // First try to parse as direct JSON (most common case)
        $data = json_decode($chunk, true);
        if ($data && isset($data['object']) && $data['object'] === 'chat.completion.chunk') {
            $result = $this->processOpenAIEvent($data);
            return $result;
        } else {
            // Fallback: Try SSE format parsing (for chunks with multiple events)
            $lines = explode("\n", $chunk);

            foreach ($lines as $line) {
                $line = trim($line);

                if (strpos($line, 'data: ') === 0) {
                    $jsonData = substr($line, 6);
                    
                    // Skip empty chunks or "[DONE]" markers
                    if (empty($jsonData) || $jsonData === '[DONE]') {
                        if ($jsonData === '[DONE]') {
                            $isDone = true;
                        }
                        continue;
                    }

                    $data = json_decode($jsonData, true);
                    if ($data && isset($data['object']) && $data['object'] === 'chat.completion.chunk') {
                        $result = $this->processOpenAIEvent($data);
                        $content .= $result['content']['text']; // Accumulate content from multiple events
                        if ($result['isDone']) $isDone = true;
                        if ($result['usage']) $usage = $result['usage'];
                    } else {
                        // Log unprocessable chunks for debugging
                        Log::debug('OpenAI unparseable chunk', [
                            'chunk' => substr($jsonData, 0, 200),
                            'json_error' => json_last_error_msg()
                        ]);
                    }
                }
            }
        }

        return [
            'content' => [
                'text' => $content,
            ],
            'isDone' => $isDone,
            'usage' => $usage
        ];
    }

    /**
     * Process a single OpenAI event (either from direct JSON or SSE)
     *
     * @param array $data
     * @return array
     */
    private function processOpenAIEvent(array $data): array
    {
        $content = '';
        $isDone = false;
        $usage = null;

        // Check for the finish_reason flag
        if (isset($data['choices'][0]['finish_reason']) && $data['choices'][0]['finish_reason'] === 'stop') {
            $isDone = true;
        }

        // Extract usage data if available
        if (!empty($data['usage'])) {
            $usage = $this->extractUsage($data);
            
            // Only log usage data if enabled in configuration (with fallback)
            try {
                if (function_exists('config') && config('logging.triggers.usage', false)) {
                    Log::info('OpenAI Usage', ['model' => $data['model'], 'usage' => $usage]);
                }
            } catch (\Exception $e) {
                // Ignore config errors in standalone testing
            }
        }

        // Extract content if available
        if (isset($data['choices'][0]['delta']['content'])) {
            $content = $data['choices'][0]['delta']['content'];
        }

        return [
            'content' => [
                'text' => $content,
            ],
            'isDone' => $isDone,
            'usage' => $usage
        ];
    }
    
    /**
     * Extract usage information from OpenAI response
     *
     * @param array $data
     * @return array|null
     */
    protected function extractUsage(array $data): ?array
    {
        if (empty($data['usage'])) {
            return null;
        }
        
        return [
            'prompt_tokens' => $data['usage']['prompt_tokens'],
            'completion_tokens' => $data['usage']['completion_tokens'],
        ];
    }
    
    /**
     * Make a non-streaming request to the OpenAI API
     *
     * @param array $payload The formatted payload
     * @return mixed The response
     */
    public function makeNonStreamingRequest(array $payload)
    {
        // Ensure stream is set to false
        $payload['stream'] = false;
        
        // Get the chat endpoint URL from database configuration
        $chatUrl = $this->getChatEndpointUrl();
        
        // Initialize cURL with the database-configured URL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $chatUrl);
        
        // Set common cURL options
        $this->setCommonCurlOptions($ch, $payload, $this->getHttpHeaders());
        
        // Execute the request
        $response = curl_exec($ch);
        
        // Handle errors
        if (curl_errno($ch)) {
            $error = 'Error: ' . curl_error($ch);
            curl_close($ch);
            return response()->json(['error' => $error], 500);
        }
        
        curl_close($ch);
        
        return response($response)->header('Content-Type', 'application/json');
    }
    
    /**
     * Make a streaming request to the OpenAI API
     *
     * @param array $payload The formatted payload
     * @param callable $streamCallback Callback for streaming responses
     * @return void
     */
    public function makeStreamingRequest(array $payload, callable $streamCallback)
    {
        // Ensure stream is set to true
        $payload['stream'] = true;
        // Enable usage reporting
        $payload['stream_options'] = [
            'include_usage' => true,
        ];
        
        set_time_limit(120);
        
        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');
        
        // Get the chat endpoint URL from database configuration
        $chatUrl = $this->getChatEndpointUrl();
        
        // Initialize cURL with the database-configured URL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $chatUrl);
        
        // Set common cURL options
        $this->setCommonCurlOptions($ch, $payload, $this->getHttpHeaders(true));
        
        // Set streaming-specific options
        $this->setStreamingCurlOptions($ch, $streamCallback);
        
        // Execute the cURL session
        curl_exec($ch);
        
        // Handle errors
        if (curl_errno($ch)) {
            $streamCallback('Error: ' . curl_error($ch));
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
     * Handle special formatting requirements for specific models
     *
     * @param string $modelId
     * @param array $messages
     * @return array
     */
    protected function handleModelSpecificFormatting(string $modelId, array $messages): array
    {
        // Special case for o1-mini: convert system to user
        if ($modelId === 'o1-mini' && isset($messages[0]) && $messages[0]['role'] === 'system') {
            $messages[0]['role'] = 'user';
        }
        
        return $messages;
    }

}