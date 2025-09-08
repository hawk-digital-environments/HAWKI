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
                'auxiliaries' => $message['auxiliaries'] ?? [],
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
        foreach ($messages as $message) {
            $contentText = $message['content']['text'] ?? '';
            
            $input[] = [
                'role' => $message['role'],
                'content' => $contentText
            ];

            $auxiliaries = $message['auxiliaries'] ?? [];
            
            foreach($auxiliaries as $aux) {
                // only handle auxiliaries of the correct type
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
            'input' => $input,
             // keep stream flag; streaming handled by makeStreamingRequest
            'stream' => !empty($rawPayload['stream']) && $this->supportsStreaming($modelId),
            'store' => false, // always false for data safety, otherwise OpenAI retain may responses
        ];


        // add aditional configuration options
        $config = $this->config;
        $modelConfig = [];
        foreach ($config['models'] as $conf) {
            if ($conf['id'] == $modelId) {
                $modelConfig = $conf;
                break;
            }
        }

        // set the reasoning effort
        if (isset($modelConfig['reasoning_effort'])) {
            $payload['reasoning'] = ['effort' => $modelConfig['reasoning_effort']];
        }

        // keep encrypted reasoning tokens if requested
        if (isset($config['keep_reasoning_tokens']) && $config['keep_reasoning_tokens']) {
            $payload['include'] = ["reasoning.encrypted_content"];
        }
        
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

        $texts = [];
        $reasoning = [];
        
        if (!empty($jsonContent['output']) && is_array($jsonContent['output'])) {
            foreach ($jsonContent['output'] as $outputItem) {
                // this is the actual model output
                if ($outputItem['type'] == "message") {
                    if (!empty($outputItem['content']) && is_array($outputItem['content'])) {
                            foreach ($outputItem['content'] as $c) {
                                if ($c['type'] == 'output_text') {
                                    $texts[] = $c['text'];
                                }
                            }
                        }
                } else if ($outputItem['type'] == "reasoning") {
                    // here we get encrypted reasoning tokens
                    // for input we'll need the entire data structure
                    $reasoning[] = $outputItem;
                }
            }
        }

        $contentText = implode('', $texts);

        return [
            'content' => [
                'text' => $contentText,
            ],
            'usage' => $this->extractUsage($jsonContent),
            // we don't need this in the CLI, hence we pass an encoded vesion
            'auxiliaries' => [
                [
                    'type' => 'openAiResponsesSpecific',
                    'content' => json_encode(["reasoning" => $reasoning]),
                ]
            ],
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
            ];
        }

        // The Responses streaming events often include a "type" field.
        // Completed event:
        $reasoning = [];
        if (isset($jsonChunk['type']) && in_array($jsonChunk['type'], ['response.completed', 'response.refreshed'], true)) {
            $isDone = true;
            // check for encrypted reasoning tokens
            $output = $jsonChunk['response']['output'];
            foreach($output as $item) {
                if ($item['type'] == 'reasoning' && isset($item['encrypted_content'])) {
                    $reasoning[] = $item;
                }
            }
        }

        // Delta-style updates may include output/content deltas
        if (isset($jsonChunk['type']) && in_array($jsonChunk['type'], ['response.output_text.delta'], true)) {
           $content = $jsonChunk['delta'];
        }

        // Extract usage data if available
        if (!empty($jsonChunk['usage'])) {
            $usage = $this->extractUsage($jsonChunk);
        } elseif (!empty($jsonChunk['metadata']['usage'])) {
            $usage = $this->extractUsage($jsonChunk['metadata']['usage']);
        }

        $responseId = '';
        if (!empty($jsonChunk['id'])) {
            $responseId = $jsonChunk['id'];
        }
        

        $response = [
            'content' => [
                'text' => $content,
            ],
            'isDone' => $isDone,
            'usage' => $usage,
        ];

        if ($reasoning) {
            $response['auxiliaries'] = [
                [
                    'type' => 'openAiResponsesSpecific',
                    'content' => json_encode(["reasoning" => $reasoning]),
                ]
            ];
        }
        return $response;
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
