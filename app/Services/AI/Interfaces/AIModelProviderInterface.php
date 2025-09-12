<?php

namespace App\Services\AI\Interfaces;

interface AIModelProviderInterface
{
    /**
     * Format the raw payload for the AI provider's API
     */
    public function formatPayload(array $rawPayload): array;

    /**
     * Format the complete response from the AI provider
     *
     * @param  mixed  $response
     * @return array ['content' => string, 'usage' => ?array]
     */
    public function formatResponse($response): array;

    /**
     * Format a single chunk from a streaming response
     * 
     * LOW-LEVEL method: Extracts and structures raw data from SSE chunks.
     * This method should focus ONLY on data extraction and normalization.
     * 
     * @param string $chunk Raw SSE chunk data (JSON string)
     * @return array Raw structured data: ['content' => array, 'isDone' => bool, 'usage' => ?array]
     */
    public function formatStreamChunk(string $chunk): array;

    /**
     * Format a single chunk into ready-to-send messages for the frontend
     * 
     * HIGH-LEVEL method: Creates complete, frontend-ready message objects.
     * This replaces provider-specific logic in the StreamController.
     * 
     * Key responsibilities:
     * 1. Call formatStreamChunk() to get raw data
     * 2. Apply provider-specific streaming behavior 
     * 3. Create complete message objects with UI context
     * 4. Handle special cases (multiple messages, completion signals, etc.)
     * 
     * @param string $chunk Raw chunk data (same input as formatStreamChunk)
     * @param array $messageContext Context for message creation (author, model, etc.)
     * @return array Array of ready-to-send messages, each with keys: author, model, isDone, content, usage?
     */
    public function formatStreamMessages(string $chunk, array $messageContext): array;

    /**
     * Establish a connection to the AI provider's API
     *
     * @param  array  $payload  The formatted payload
     * @param  callable|null  $streamCallback  Callback for streaming responses
     * @return mixed The response or void for streaming
     */
    public function connect(array $payload, ?callable $streamCallback = null);

    /**
     * Make a non-streaming request to the AI provider
     *
     * @param  array  $payload  The formatted payload
     * @return mixed The response
     */
    public function makeNonStreamingRequest(array $payload);

    /**
     * Make a streaming request to the AI provider
     *
     * @param  array  $payload  The formatted payload
     * @param  callable  $streamCallback  Callback for streaming responses
     * @return void
     */
    public function makeStreamingRequest(array $payload, callable $streamCallback);

    /**
     * Get details for a specific model
     */
    public function getModelDetails(string $modelId): array;

    /**
     * Check if a model supports streaming
     */
    public function supportsStreaming(string $modelId): bool;

    /**
     * Fetch available models from the provider's API
     *
     * @return array Raw API response containing models
     *
     * @throws \Exception
     */
    public function fetchAvailableModelsFromAPI(): array;
}
