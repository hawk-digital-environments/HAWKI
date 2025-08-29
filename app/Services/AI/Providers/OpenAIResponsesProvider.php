<?php

namespace App\Services\AI\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class OpenAIResponsesProvider extends BaseAIModelProvider
{

    public function mapMessages(array $messages): array
    {
        $mapped = [];

        foreach ($messages as $message) {
            $mapped[] = [
                // change: map "system" -> "developer"
                'role' => ($message['role'] === 'system') ? 'developer' : $message['role'],
                'content' => $message['content'],
            ];
        }

        return $mapped;
    }
    /**
     * Format the raw payload for OpenAI Responses API
     *
     * @param array $rawPayload
     * @return array
     */
    public function formatPayload(array $rawPayload): array
    {
        $messages = $rawPayload['messages'];
        $modelId = $rawPayload['model'];
        
        // Handle special cases for specific models
        $messages = $this->mapMessages($messages);

        // Convert messages into the Responses API "input" shape
        $input = [];
        $previousMessageId = '';
        foreach ($messages as $message) {
            $contentText = $message['content']['text'] ?? '';
            $previousMessageId = $message['content']['previousMessageId'] ?? '';
            $input[] = [
                'role' => $message['role'],
                'content' => $contentText
            ];
            
        }

        // Build payload for Responses endpoint
        $payload = [
            'model' => $modelId,
            'input' => $input,
            // keep stream flag; streaming handled by makeStreamingRequest
            //'stream' => !empty($rawPayload['stream']) && $this->supportsStreaming($modelId),
            //'reasoning' => ['effort' => 'low']
        ];

        // include previous_response_id if provided (Responses API uses previous_response to continue reasoning)
        if (!empty($previousMessageId)) {
            $payload['previous_response_id'] = $previousMessageId;
        }

        // add aditional configuration options
        $config = $this->config;
        if (isset($config[$modelId]['reasoning_effort'])) {
            $payload['reasoning'] = ['effort' => $config[$modelId]['reasoning_effort']];
        }

        if (isset($config['store'])) {
            $payload['store'] = $config['store'];
        }

        if (isset($config['encrypt_reasoning_content']) && $config['encrypt_reasoning_content']) {
            $payload['include'] = ["reasoning.encrypted_content"];
        }

        error_log(print_r($payload, true));

        return $payload;
    }

    /**
     * Format the complete response from Responses API
     *
     * @param mixed $response
     * @return array
     */
    public function formatResponse($response): array
    {
        
        $responseContent = $response->getContent();
        $jsonContent = json_decode($responseContent, true);

        //error_log(print_r($jsonContent, true));

        // Extract text from the Responses "output" structure
        $texts = [];
        
        if (!empty($jsonContent['output']) && is_array($jsonContent['output'])) {
            foreach ($jsonContent['output'] as $outputItem) {
                
                
                if (!empty($outputItem['content']) && is_array($outputItem['content'])) {
                    if ($outputItem['type'] == "message") {
                        foreach ($outputItem['content'] as $c) {
                            if (is_string($c)) {
                                $texts[] = $c;
                            } elseif (!empty($c['text'])) {
                                $texts[] = $c['text'];
                            }
                        }
                    }
                }
            
            }
        }

        $contentText = implode('', $texts);

        return [
            'content' => [
                'text' => $contentText,
                'previousMessageId' => $jsonContent['id'],
            ],
            'usage' => $this->extractUsage($jsonContent),
            
        ];
    }

    /**
     * Format a single chunk from a streaming Responses API stream
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

        if (empty($jsonChunk) || !is_array($jsonChunk)) {
            return [
                'content' => ['text' => ''],
                'isDone' => false,
                'usage' => null,
                'raw' => $chunk,
            ];
        }

        // The Responses streaming events often include a "type" field.
        // Completed event:
        if (isset($jsonChunk['type']) && in_array($jsonChunk['type'], ['response.completed', 'response.refreshed'], true)) {
            $isDone = true;
        }

        // Delta-style updates may include output/content deltas
        // Try to collect any text we find in a few common locations
        if (!empty($jsonChunk['delta'])) {
            // delta may mirror the output structure
            $delta = $jsonChunk['delta'];
            if (!empty($delta['content']) && is_array($delta['content'])) {
                foreach ($delta['content'] as $c) {
                    if (is_string($c)) {
                        $content .= $c;
                    } elseif (!empty($c['text'])) {
                        $content .= $c['text'];
                    }
                }
            } elseif (!empty($delta['text'])) {
                $content .= $delta['text'];
            }
        }

        // Some stream chunks may include an 'output' directly
        if (empty($content) && !empty($jsonChunk['output']) && is_array($jsonChunk['output'])) {
            foreach ($jsonChunk['output'] as $outputItem) {
                if (!empty($outputItem['content']) && is_array($outputItem['content'])) {
                    foreach ($outputItem['content'] as $c) {
                        if (is_string($c)) {
                            $content .= $c;
                        } elseif (!empty($c['text'])) {
                            $content .= $c['text'];
                        }
                    }
                }
            }
        }

        // Extract usage data if available
        if (!empty($jsonChunk['usage'])) {
            $usage = $this->extractUsage($jsonChunk);
        } elseif (!empty($jsonChunk['metadata']['usage'])) {
            $usage = $this->extractUsage($jsonChunk['metadata']['usage']);
        }

        return [
            'content' => [
                'text' => $content,
            ],
            'isDone' => $isDone,
            'usage' => $usage,
            'raw' => $jsonChunk,
        ];
    }

    /**
     * Extract usage information from Responses API output
     *
     * @param array $data
     * @return array|null
     */
    protected function extractUsage(array $data): ?array
    {
        // Responses API usage may appear under top-level 'usage' or nested structures.
        if (empty($data)) {
            return null;
        }

        if (!empty($data['usage']) && is_array($data['usage'])) {
            $usage = $data['usage'];
            return [
                'prompt_tokens' => $usage['prompt_tokens'] ?? ($usage['input_tokens'] ?? null),
                'completion_tokens' => $usage['completion_tokens'] ?? ($usage['output_tokens'] ?? null),
            ];
        }

        // Fallback: some events may include usage-like fields under metadata
        if (!empty($data['metadata']) && !empty($data['metadata']['usage'])) {
            $usage = $data['metadata']['usage'];
            return [
                'prompt_tokens' => $usage['prompt_tokens'] ?? null,
                'completion_tokens' => $usage['completion_tokens'] ?? null,
            ];
        }

        return null;
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

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->config['api_url']);

        // Set common cURL options
        $this->setCommonCurlOptions($ch, $payload, $this->getHttpHeaders());

        // Execute the request
        $response = curl_exec($ch);
        //error_log(print_r($response, true));
        //error_log(print_r("1", true));

        // Handle errors
        if (curl_errno($ch)) {
            $error = 'Error: ' . curl_error($ch);
            curl_close($ch);
            return response()->json(['error' => $error], 500);
        }
         //error_log(print_r("2", true));
        curl_close($ch);
         //error_log(print_r("3", true));
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
