<?php

namespace App\Services\AI\Providers\Google;
use Illuminate\Support\Facades\Log;
use App\Services\AI\Providers\BaseAIModelProvider;

class GoogleProvider extends BaseAIModelProvider
{
    /**
     * Format the raw payload for Google API
     *
     * @param array $rawPayload
     * @return array
     */
    public function formatPayload(array $rawPayload): array
    {
        $formatter = new GoogleFormatter($this->config);
        $payload = $formatter->format($rawPayload);
        return $payload;
    }

    /**
     * Format the complete response from Google
     *
     * @param mixed $response
     * @return array
     */
    public function formatResponse($response): array
    {
        $responseContent = $response->getContent();
        $jsonContent = json_decode($responseContent, true);

        $content = $jsonContent['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $groundingMetadata = $jsonContent['candidates'][0]['groundingMetadata'] ?? '';

        return [
            'content' => [
                'text' => $content,
                'groundingMetadata' => $groundingMetadata,
            ],
            'usage' => $this->extractUsage($jsonContent)
        ];
    }

    /**
     * Format a single chunk from a streaming response from Google
     *
     * @param $chunk
     * @return array
     */
    public function formatStreamChunk(string $chunk): array
    {
        $jsonChunk = json_decode($chunk, true);
        $content = '';
        $groundingMetadata = '';
        $isDone = false;
        $usage = null;

        // Extract content if available
        if (isset($jsonChunk['candidates'][0]['content']['parts'][0]['text'])) {
            $content = $jsonChunk['candidates'][0]['content']['parts'][0]['text'];
        }

        // Add search results
        if (isset($jsonChunk['candidates'][0]['groundingMetadata'])) {
            $groundingMetadata = $jsonChunk['candidates'][0]['groundingMetadata'];
        }

        // Check for completion
        if (isset($jsonChunk['candidates'][0]['finishReason']) &&
            $jsonChunk['candidates'][0]['finishReason'] !== 'FINISH_REASON_UNSPECIFIED') {
            $isDone = true;
        }

        // Extract usage if available
        if (isset($jsonChunk['usageMetadata'])) {
            $usage = $this->extractUsage($jsonChunk);
        }

        return [
            'content' => [
                'text' => $content,
                'groundingMetadata' => $groundingMetadata,
            ],
            'isDone' => $isDone,
            'usage' => $usage
        ];
    }

    /**
     * Extract usage information from Google response
     *
     * @param array $data
     * @return array|null
     */
    protected function extractUsage(array $data): ?array
    {
        if (empty($data['usageMetadata'])) {
            return null;
        }
        // fix duplicate usage log entries
        if (!empty($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] === "STOP") {
            return [
                'prompt_tokens' => $data['usageMetadata']['promptTokenCount'] ?? 0,
                'completion_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? 0,
            ];
        }
        return null;
    }

    /**
     * Override common HTTP headers for Google API requests without Authorization header
     *
     * @param bool $isStreaming Whether this is a streaming request
     * @return array
     */
     protected function getHttpHeaders(bool $isStreaming = false): array
    {
        $headers = [
            'Content-Type: application/json'
        ];

        return $headers;
    }

    /**
     * Make a non-streaming request to the Google API
     *
     * @param array $payload
     * @return mixed
     */
    public function makeNonStreamingRequest(array $payload)
    {
        // Ensure stream is set to false
        $payload['stream'] = false;

        // Construct the URL with API key
        $url = $this->config['api_url'] . $payload['model'] . ':generateContent?key=' . $this->config['api_key'];
        // Extract just the necessary parts for Google's API
        $requestPayload = [
            'system_instruction' => $payload['system_instruction'],
            'contents' => $payload['contents']
        ];

        // Add aditional config parameters if present
        if (isset($payload['safetySettings'])) {
            $requestPayload['safetySettings'] = $payload['safetySettings'];
        }
        if (isset($payload['generationConfig'])) {
            $requestPayload['generationConfig'] = $payload['generationConfig'];
        }
        if (isset($payload['tools'])) {
            $requestPayload['tools'] = $payload['tools'];
        }

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        // Set common cURL options
        $this->setCommonCurlOptions($ch, $requestPayload, $this->getHttpHeaders());

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
        // Streaming endpoint for Google Gemini
        $url = $this->config['stream_url'] . $payload['model'] . ':streamGenerateContent?key=' . $this->config['api_key'];

        // Extract necessary parts for Google's API
        $requestPayload = [
            'system_instruction' => $payload['system_instruction'],
            'contents' => $payload['contents']
        ];

        // Add aditional config parameters if present
        if (isset($payload['safetySettings'])) {
            $requestPayload['safetySettings'] = $payload['safetySettings'];
        }
        if (isset($payload['generationConfig'])) {
            $requestPayload['generationConfig'] = $payload['generationConfig'];
        }
        if (isset($payload['tools'])) {
            $requestPayload['tools'] = $payload['tools'];
        }

        set_time_limit(120);

        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        // Set common cURL options
        $this->setCommonCurlOptions($ch, $requestPayload, $this->getHttpHeaders(true));

        // Set streaming-specific options
        $this->setStreamingCurlOptions($ch, $streamCallback);

            // Log the full curl command (simulated)
            $httpHeaders = $this->getHttpHeaders(true);
            $headerString = '';
            foreach ($httpHeaders as $header) {
                $headerString .= "-H '" . $header . "' ";
            }
            $command = "curl -X POST '" . $url . "' " . $headerString . "-d '" . json_encode($requestPayload) . "'";

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
