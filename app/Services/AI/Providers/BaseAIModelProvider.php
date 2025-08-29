<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Interfaces\AIModelProviderInterface;
use Illuminate\Support\Facades\Log;

abstract class BaseAIModelProvider implements AIModelProviderInterface
{
    /**
     * Provider configuration from config/model_providers.php
     *
     * @var array
     */
    protected $config;
    protected $utils;

    /**
     * Create a new provider instance
     *
     * @param array $config Provider configuration
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->utils = new ModelUtilities($config);
    }

    /**
     * Extract usage information from the response data
     *
     * @param array $data Response data
     * @return array|null Usage data or null if not available
     */
    protected function extractUsage(array $data): ?array
    {
        return null;
    }

    /**
     * Establish a connection to the AI provider's API
     *
     * @param array $payload The formatted payload
     * @param callable|null $streamCallback Callback for streaming responses
     * @return mixed The response or void for streaming
     */
    public function connect(array $payload, ?callable $streamCallback = null)
    {
        $modelId = $payload['model'];
        // Determine whether to use streaming or non-streaming
        if ($streamCallback && $this->utils->hasTool($modelId, 'stream')) {
            return $this->makeStreamingRequest($payload, $streamCallback);
        } else {
            return $this->makeNonStreamingRequest($payload);
        }
    }

    /**
     * Set up common HTTP headers for API requests
     *
     * @param bool $isStreaming Whether this is a streaming request
     * @return array
     */
    protected function getHttpHeaders(bool $isStreaming = false): array
    {
        $headers = [
            'Content-Type: application/json'
        ];

        // Add authorization header if API key is present
        if (!empty($this->config['api_key'])) {
            $headers[] = 'Authorization: Bearer ' . $this->config['api_key'];
        }

        return $headers;
    }

    /**
     * Set common cURL options for all requests
     *
     * @param resource $ch cURL resource
     * @param array $payload Request payload
     * @param array $headers HTTP headers
     * @return void
     */
    protected function setCommonCurlOptions($ch, array $payload, array $headers): void
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
     * @param resource $ch cURL resource
     * @param callable $streamCallback Callback for processing chunks
     * @return void
     */
    protected function setStreamingCurlOptions($ch, callable $streamCallback): void
    {
        // Set timeout parameters for streaming
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1);
        curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 20);

        // Process each chunk as it arrives
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($streamCallback) {
            if (connection_aborted()) {
                return 0;
            }

            $streamCallback($data);

            if (ob_get_length()) {
                ob_flush();
            }
            flush();

            return strlen($data);
        });
    }
}
