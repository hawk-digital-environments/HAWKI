<?php

namespace App\Services\AI\Providers\Ollama;
use App\Services\AI\Providers\BaseAIModelProvider;

use Illuminate\Support\Facades\Log;

class OllamaProvider extends BaseAIModelProvider
{
    /**
     * Format the raw payload for Ollama API
     *
     * @param array $rawPayload
     * @return array
     */
    public function formatPayload(array $rawPayload): array
    {
        $formatter = new OllamaFormatter($this->config);
        $payload = $formatter->format($rawPayload);
        return $payload;
    }

    /**
     * Format the complete response from Ollama
     *
     * @param mixed $response
     * @return array
     */
    public function formatResponse($response): array
    {
        $responseContent = $response->getContent();
        $jsonContent = json_decode($responseContent, true);

        // Extract content based on Ollama's response format
        $content = $jsonContent['message']['content'] ?? '';

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
        $jsonChunk = json_decode($chunk, true);

        $content = '';
        $isDone = false;
        $usage = null;

        // Extract content based on Ollama's streaming format
        if (isset($jsonChunk['message']['content'])) {
            $content = $jsonChunk['message']['content'];
        }

        // Check if this is the final chunk
        if (isset($jsonChunk['done']) && $jsonChunk['done'] === true) {
            $isDone = true;

            // Extract usage if available in the final chunk
            if (isset($jsonChunk['eval_count']) && isset($jsonChunk['prompt_eval_count'])) {
                $usage = $this->extractUsage($jsonChunk);
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
     * Extract usage information from Ollama response
     *
     * @param array $data
     * @return array|null
     */
    protected function extractUsage(array $data): ?array
    {
        if (!isset($data['eval_count']) || !isset($data['prompt_eval_count'])) {
            return null;
        }

        return [
            'prompt_tokens' => $data['prompt_eval_count'],
            'completion_tokens' => $data['prompt_eval_count'] - $data['eval_count'],
        ];
    }

    /**
     * Make a non-streaming request to the Ollama API
     *
     * @param array $payload
     * @return mixed
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
     * Make a streaming request to the Ollama API
     *
     * @param array $payload
     * @param callable $streamCallback
     * @return void
     */
    public function makeStreamingRequest(array $payload, callable $streamCallback)
    {
        // Implementation of streaming request for Ollama
        // Similar to OpenAI implementation but adapted for Ollama's API

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
