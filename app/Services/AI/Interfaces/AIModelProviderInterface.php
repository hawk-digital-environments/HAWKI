<?php

namespace App\Services\AI\Interfaces;

interface AIModelProviderInterface
{
    /**
     * Format the raw payload for the AI provider's API
     *
     * @param array $rawPayload
     * @return array
     */
    public function formatPayload(array $rawPayload): array;

    /**
     * Format the complete response from the AI provider
     *
     * @param mixed $response
     * @return array ['content' => string, 'usage' => ?array]
     */
    public function formatResponse($response): array;

    /**
     * Format a single chunk from a streaming response
     *
     * @param string $chunk
     * @return array ['content' => string, 'isDone' => bool, 'usage' => ?array]
     */
    public function formatStreamChunk(string $chunk): array;

    /**
     * Establish a connection to the AI provider's API
     *
     * @param array $payload The formatted payload
     * @param callable|null $streamCallback Callback for streaming responses
     * @return mixed The response or void for streaming
     */
    public function connect(array $payload, ?callable $streamCallback = null);

    /**
     * Make a non-streaming request to the AI provider
     *
     * @param array $payload The formatted payload
     * @return mixed The response
     */
    public function makeNonStreamingRequest(array $payload);

    /**
     * Make a streaming request to the AI provider
     *
     * @param array $payload The formatted payload
     * @param callable $streamCallback Callback for streaming responses
     * @return void
     */
    public function makeStreamingRequest(array $payload, callable $streamCallback);



}
