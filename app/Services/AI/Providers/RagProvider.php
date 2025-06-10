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
        Log::debug($response);

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

        Log::debug($payload);

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
    public function connect(array $payload, ?callable $streamCallback = null)
    {
        // Add model for BaseAIModelProvider internal use
        $internalPayload = array_merge($payload, ['model' => $this->getModelIdentifier()]);
        
        // Call parent's connect method with the internal payload
        return parent::connect($internalPayload, $streamCallback);
    }

    /**
     * Override the makeStreamingRequest to ensure only allowed parameters are sent to Vector DB
     */
    public function makeStreamingRequest(array $payload, callable $streamCallback)
    {
        // Strip down to only the parameters Vector DB accepts
        $vectorDbPayload = [
            'messages' => $payload['messages'],
            'stream' => true
        ];
        
        try {
            set_time_limit(600);
            
            // Buffer control
            if (ob_get_level()) ob_end_clean();
            
            // Set headers for SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');
            header('Access-Control-Allow-Origin: *');
            
            $ch = curl_init();
            
            if ($ch === false) {
                throw new \Exception('Failed to initialize cURL');
            }
            
            $apiUrl = config('model_providers.providers.rag.api_url');
            $apiKey = config('model_providers.providers.rag.api_key');
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($vectorDbPayload),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                    'Accept: text/event-stream'
                ],
                CURLOPT_WRITEFUNCTION => function($curl, $data) use ($streamCallback) {
                    $lines = explode("\n", $data);
                    foreach ($lines as $line) {
                        if (trim($line)) {
                            try {
                                // Pass through each line
                                $streamCallback($line . "\n");
                                
                                // Immediately flush
                                if (ob_get_level() > 0) {
                                    ob_flush();
                                }
                                flush();
                            } catch (\Exception $e) {
                                Log::error('Stream callback error:', [
                                    'error' => $e->getMessage(),
                                    'data' => $line
                                ]);
                            }
                        }
                    }
                    return strlen($data);
                }
            ]);
            
            $success = curl_exec($ch);
            
            if ($success === false) {
                throw new \Exception('cURL Error: ' . curl_error($ch));
            }
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode !== 200) {
                throw new \Exception('HTTP Error: ' . $httpCode);
            }
            
        } catch (\Exception $e) {
            Log::error('Streaming request failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Send error in the same format as successful responses
            $errorResponse = json_encode([
                'choices' => [
                    [
                        'delta' => [
                            'content' => 'Error: ' . $e->getMessage()
                        ],
                        'finish_reason' => 'stop'
                    ]
                ]
            ]) . "\n";
            
            $streamCallback($errorResponse);
        } finally {
            if (isset($ch)) {
                curl_close($ch);
            }
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }
    }
}