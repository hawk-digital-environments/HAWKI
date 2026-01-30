<?php
declare(strict_types=1);


namespace App\Services\AI\Providers;


use App\Services\AI\Utils\StreamChunkHandler;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;
use JsonException;

abstract class AbstractRequest
{
    protected bool $wasConnectionAborted = false;
    
    /**
     * Check if the connection was aborted by the client
     */
    public function wasConnectionAborted(): bool
    {
        return $this->wasConnectionAborted;
    }
    
    /**
     * Reset the abort status (useful for reusing request objects)
     */
    public function resetAbortStatus(): void
    {
        $this->wasConnectionAborted = false;
    }
    
    /**
     * Executes a streaming request to the AI model.
     *
     * @param AiModel $model The AI model to interact with.
     * @param array $payload The request payload to send.
     * @param callable(AiResponse $response): void $onData Callback executed for each chunk of data received.
     * @param callable(AiModel $model, string $chunk): AiResponse $chunkToResponse Callback to transform a chunk into a response.
     * @param callable():array|null $getHttpHeaders Optional callback to generate HTTP headers.
     * @param string|null $apiUrl Optional API URL to override the model's default.
     * @param int|null $timeout Optional timeout for the request in seconds.
     * @return void
     */
    protected function executeStreamingRequest(
        AiModel   $model,
        array     $payload,
        callable  $onData,
        callable  $chunkToResponse,
        ?callable $getHttpHeaders = null,
        ?string   $apiUrl = null,
        ?int      $timeout = null
    ): void
    {
        // Extended timeout for streaming requests (especially for web search & reasoning)
        // Responses API with web search can take 5+ minutes
        set_time_limit($timeout ?? 600);

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl ?? $model->getProvider()->getConfig()->getStreamUrl());

        // Set common cURL options
        $headers = is_callable($getHttpHeaders) ? $getHttpHeaders($model) : $this->getHttpHeaders($model);
        $this->setCommonCurlOptions($ch, $payload, $headers);

        // Set streaming-specific options
        $this->setStreamingCurlOptions($ch, function (string $chunk) use ($model, $onData, $chunkToResponse) {
            // Log the chunk data for debugging (if enabled)
            if (config('logging.triggers.curl_return_object')) {
                \Log::info(trim($chunk));
            }
            $onData($chunkToResponse($model, $chunk));
        });

        // Execute the cURL session
        curl_exec($ch);

        // Handle errors
        if (curl_errno($ch)) {
            $onData($this->createErrorResponse(curl_error($ch)));
        }

        curl_close($ch);
    }

    /**
     * Executes a non-streaming request to the AI model.
     *
     * @param AiModel $model The AI model to interact with.
     * @param array $payload The request payload to send.
     * @param callable(array $data): AiResponse $dataToResponse Callback to transform the data into a response.
     * @param callable|null $getHttpHeaders Optional callback to generate HTTP headers.
     * @param string|null $apiUrl Optional API URL to override the model's default.
     * @param int|null $timeout Optional timeout for the request in seconds.
     * @return AiResponse The response from the AI model.
     * @throws JsonException
     */
    protected function executeNonStreamingRequest(
        AiModel   $model,
        array     $payload,
        callable  $dataToResponse,
        ?callable $getHttpHeaders = null,
        ?string   $apiUrl = null,
        ?int      $timeout = null
    ): AiResponse
    {
        // Extended timeout for non-streaming requests
        set_time_limit($timeout ?? 300);

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl ?? $model->getProvider()->getConfig()->getApiUrl());
        // Set common cURL options
        $headers = is_callable($getHttpHeaders) ? $getHttpHeaders($model) : $this->getHttpHeaders($model);
        $this->setCommonCurlOptions($ch, $payload, $headers);
        
        // Execute the request
        $response = curl_exec($ch);

        // Handle errors
        if (curl_errno($ch)) {
            $error = 'Error: ' . curl_error($ch);
            curl_close($ch);
            return $this->createErrorResponse($error);
        }

        curl_close($ch);

        // Debug logging for non-streaming responses
        if (config('logging.triggers.curl_return_object')) {
            \Log::info('[NON-STREAMING] Raw API Response' . $response);
        }

        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        return $dataToResponse($data);
    }

    /**
     * Create a standardized error response
     * @param string $error
     * @return AiResponse
     */
    protected function createErrorResponse(string $error): AiResponse
    {
        return new AiResponse(
            content: [
                'text' => 'INTERNAL ERROR: ' . $error,
                'error' => $error
            ],
            error: $error,
        );
    }

    /**
     * Set up common HTTP headers for API requests
     *
     * @param AiModel $model The model to request information for
     * @return array
     */
    protected function getHttpHeaders(AiModel $model): array
    {
        $headers = [
            'Content-Type: application/json'
        ];

        $apiKey = $model->getProvider()->getConfig()->getApiKey();
        // Add authorization header if API key is present
        if ($apiKey !== null) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        return $headers;
    }

    /**
     * Set common cURL options for all requests
     *
     * @param \CurlHandle $ch cURL resource
     * @param array $payload Request payload
     * @param array $headers HTTP headers
     * @return void
     */
    protected function setCommonCurlOptions(\CurlHandle $ch, array $payload, array $headers): void
    {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    }

    /**
     * Set up streaming-specific cURL options
     *
     * @param \CurlHandle $ch cURL resource
     * @param callable $onData A callable execute for every chunk received
     * @return void
     */
    protected function setStreamingCurlOptions(\CurlHandle $ch, callable $onData): void
    {
        // Set timeout parameters for streaming
        // CURLOPT_TIMEOUT = 0: No maximum time limit (allows long-running operations)
        // LOW_SPEED_LIMIT = 1: Minimum 1 byte/second transfer rate
        // LOW_SPEED_TIME = 120: Allow up to 2 minutes of inactivity (e.g., during web search/reasoning)
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1);
        curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 120);

        $chunkHandler = new StreamChunkHandler($onData);
        $connectionAborted = false; // Track if connection was aborted

        // Process each chunk as it arrives
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($ch, $data) use ($chunkHandler, &$connectionAborted) {
            if (connection_aborted()) {
                $connectionAborted = true;
                return 0;
            }

            // Log raw CURL data BEFORE StreamChunkHandler processes it
            if (config('logging.triggers.raw_curl_chunk')) {
                \Log::info('[RAW] ' . $data);
            }

            $chunkHandler->handle($data);

            return strlen($data);
        });
        
        // Store abort status for later retrieval
        $this->wasConnectionAborted = &$connectionAborted;
    }
}
