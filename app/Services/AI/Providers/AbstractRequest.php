<?php
declare(strict_types=1);


namespace App\Services\AI\Providers;


use App\Services\AI\Utils\StreamChunkHandler;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiResponse;
use JsonException;

abstract class AbstractRequest
{
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
        set_time_limit($timeout ?? 120);

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl ?? $model->getProvider()->getConfig()->getStreamUrl());

        // Set common cURL options
        $headers = is_callable($getHttpHeaders) ? $getHttpHeaders($model) : $this->getHttpHeaders($model);
        $this->setCommonCurlOptions($ch, $payload, $headers);

        // Set streaming-specific options
        $this->setStreamingCurlOptions($ch, function (string $chunk) use ($model, $onData, $chunkToResponse) {
//            \Log::debug($chunk);
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
        set_time_limit($timeout ?? 120);

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
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1);
        curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 20);

        $chunkHandler = new StreamChunkHandler($onData);

        // Process each chunk as it arrives
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($ch, $data) use ($chunkHandler) {
            if (connection_aborted()) {
                return 0;
            }

            $chunkHandler->handle($data);

            return strlen($data);
        });
    }
}
