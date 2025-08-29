<?php

namespace App\Services\AI\Providers\OpenAI;

use App\Services\AI\Providers\BaseAIModelProvider;
use App\Services\AI\Providers\OpenAI\OpenAIFormatter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class  OpenAIProvider extends BaseAIModelProvider
{
    /**
     * Format the raw payload for OpenAI API
     *
     * @param array $rawPayload
     * @return array
     */
    public function formatPayload(array $rawPayload): array
    {
        $formatter = new OpenAIFormatter($this->config);
        $payload = $formatter->format($rawPayload);
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
        $jsonChunk = json_decode($chunk, true);

        $content = '';
        $isDone = false;
        $usage = null;

        // Check for the finish_reason flag
        if (isset($jsonChunk['choices'][0]['finish_reason']) && $jsonChunk['choices'][0]['finish_reason'] === 'stop') {
            $isDone = true;
        }

        // Extract usage data if available
        if (!empty($jsonChunk['usage'])) {
            $usage = $this->extractUsage($jsonChunk);
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




    // /**
    //  * Ping the API to check model status
    //  *
    //  * @param string $modelId
    //  * @return string
    //  * @throws \Exception
    //  */
    // public function getModelsStatus(): array
    // {
    //     $response = $this->pingProvider();
    //     $stats = json_decode($response, true)['data'];
    //     return $stats;
    // }

    // /**
    // * Ping the API to check status of all models
    // */
    // public function getModelsList(): string
    // {
    //     $response = $this->pingProvider();
    //     $stats = json_decode($response, true)['data'];
    //     return $stats;
    // }


    // /**
    //  * Get status of all models
    //  *
    //  * @return string
    //  */
    // protected function pingProvider(): string
    // {
    //     $url = $this->config['ping_url'];
    //     $apiKey = $this->config['api_key'];

    //     try {
    //         $response = Http::withToken($apiKey)
    //             ->timeout(5) // Set a short timeout
    //             ->get($url);

    //         return $response;
    //     } catch (\Exception $e) {
    //         return null;
    //     }

    //     return $statuses;
    // }
}
