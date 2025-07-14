<?php

namespace App\Services\AI\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;



class RagProvider extends BaseAIModelProvider
{
    /**
     * Format the raw payload for Vector DB
     *
     * @param array $rawPayload
     * @return array
     */
    public function formatPayload(array $rawPayload): array
    {
        $messages = $rawPayload['messages'];
        $modelId = $rawPayload['model'];

        // Create an array of message histories with role and content without system messages
        $messageHistory = array_values(array_filter(
            array_map(function($message) {
                return [
                    'role' => $message['role'],
                    'content' => $message['content']['text']
                ];
            }, $messages),
            function($message) {
                return $message['role'] !== 'system';
            }
        ));
        Log::info('Vector DB Request:', $messageHistory);

        // Return the payload that will be sent to Vector DB
        return [
            'model' => $modelId,
            'messages' => $messageHistory,
            'stream' => true
        ];
    }

    /**
     * Format the complete response from Vector DB
     *
     * @param mixed $response
     * @return array
     */
    public function formatResponse($response): array
    {

        if (isset($response['chat_response'])) {
            $formattedContent = (string) trim($response['chat_response']);  // Just the string

            // Log the formatted content
            Log::info('Formatted Content:', ['content' => $formattedContent]);
        }

        // Log if response doesn't contain chat_response
        if (!isset($response['chat_response'])) {
            Log::warning('Missing chat_response in response data');
        }

        return [
            'content' => $formattedContent ?? 'No response received'
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
        $jsonChunk = json_decode($chunk, true);

        $content = '';
        $isDone = false;
        $usage = null;
        $type = null;
        // Check for the finish_reason flag
        if (isset($jsonChunk['choices'][0]['finish_reason']) && $jsonChunk['choices'][0]['finish_reason'] === 'stop') {
            $isDone = true;
        }

        // Extract usage data if available
        if (!empty($jsonChunk['usage'])) {
            $usage = $this->extractUsage($jsonChunk);
            Log::info('Vector DB', ['model' => $jsonChunk['model'], 'usage' => $usage]);
        }

        if(isset($jsonChunk['type']) && !empty($jsonChunk['type'])){
            if($jsonChunk['type'] === 'ragStatus'){{
                $type = 'status';
            }}
            else{
                $type = 'message';
            }
        }

        // Extract content if available
        if (isset($jsonChunk['choices'][0]['delta']['content'])) {
            $content = $jsonChunk['choices'][0]['delta']['content'];
        }

        return [
            'content' => [
                'text' => $content,
            ],
            'isDone' => $isDone,
            'usage' => $usage,
            'type' => $type
        ];
    }

    /**
     * Extract usage information from Vector DB response
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
     * Make a non-streaming request to the Vector DB API
     *
     * @param array $payload The formatted payload
     * @return mixed The response
     */
    public function makeNonStreamingRequest(array $payload)
    {

        $payload = [
            'messages'=>$payload['messages'],
            'term'=>null
        ];


        // Set PHP execution time limit
        set_time_limit(600); // Set to 2 minutes

        // Make direct request to vector-db endpoint with authorization
        $response = Http::timeout(90)  // Set HTTP timeout to 90 seconds
            ->withHeaders([
                'Authorization' => 'Bearer ' . config('model_providers.providers.rag.api_key'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->retry(3, 100) // Add retry logic
            ->post(config('model_providers.providers.rag.api_url'), $payload);

        if (!$response->successful()) {
            Log::error('Vector DB error response:', $response->json());
            throw new \Exception('Vector DB request failed');
        }

        return $response;
    }

    /**
     * Get the model identifier for this provider
     * This is required by BaseAIModelProvider but not sent to Vector DB
     *
     * @return string
     */
    protected function getModelIdentifier(): string
    {
        return 'vector-db';
    }

    /**
     * Override the connect method from BaseAIModelProvider to handle the payload transformation
     *
     * @param array $payload
     * @param callable|null $streamCallback
     * @return mixed
     */
    // public function connect(array $payload, ?callable $streamCallback = null)
    // {
    //     // Add model for BaseAIModelProvider internal use
    //     $internalPayload = array_merge($payload, ['model' => $this->getModelIdentifier()]);

    //     // Call parent's connect method with the internal payload
    //     return parent::connect($internalPayload, $streamCallback);
    // }

    /**
     * Override the makeStreamingRequest to ensure only allowed parameters are sent to Vector DB
     */

    public function makeStreamingRequest(array $payload, callable $streamCallback)
    {
        // Ensure stream is set to true
        $payload = [
            'messages' => $payload['messages'],
            'stream' => true
        ];

        set_time_limit(120);

        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->config['api_url']);

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


}
