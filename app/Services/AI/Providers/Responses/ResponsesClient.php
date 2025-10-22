<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\Responses;

use App\Services\AI\Providers\AbstractClient;
use App\Services\AI\Providers\Responses\Request\ResponsesStreamingRequest;
use App\Services\AI\Value\AiModelStatusCollection;
use App\Services\AI\Value\AiRequest;
use App\Services\AI\Value\AiResponse;

class ResponsesClient extends AbstractClient
{
    public function __construct(
        private readonly ResponsesRequestConverter $converter
    )
    {
    }

    /**
     * Execute non-streaming request (NOT SUPPORTED for Responses API)
     * 
     * Responses API only supports streaming mode
     * 
     * @throws \Exception
     */
    protected function executeRequest(AiRequest $request): AiResponse
    {
        throw new \Exception('Non-streaming mode is not supported for Responses API. Use streaming only.');
    }

    /**
     * Execute streaming request to Responses API
     * 
     * Implements graceful fallback for previous_response_id errors:
     * If the API returns "Previous response not found", automatically retry without previous_response_id
     * 
     * @inheritDoc
     */
    protected function executeStreamingRequest(AiRequest $request, callable $onData): void
    {
        // Convert request to payload
        $payload = $this->converter->convertRequestToPayload($request);
        
        // Track if we should retry without previous_response_id
        $hasPreviousResponseId = isset($payload['previous_response_id']);
        $previousResponseId = $payload['previous_response_id'] ?? null;
        $errorOccurred = false;
        $errorMessage = null;

        // Wrap onData to detect "Previous response not found" errors
        $wrappedOnData = function($response) use (&$onData, &$errorOccurred, &$errorMessage, $previousResponseId) {
            // Check if this is an error response
            if ($response->error && str_contains($response->error, 'Previous response') && str_contains($response->error, 'not found')) {
                $errorOccurred = true;
                $errorMessage = $response->error;
                
                // Don't call original onData yet - we'll retry
                return;
            }
            
            // Pass through to original onData
            $onData($response);
        };

        // First attempt with original payload
        (new ResponsesStreamingRequest($payload, $wrappedOnData))->execute($request->model);

        // If error occurred and we had previous_response_id, retry without it
        if ($errorOccurred && $hasPreviousResponseId) {
            // Remove previous_response_id and retry (graceful fallback)
            unset($payload['previous_response_id']);
            (new ResponsesStreamingRequest($payload, $onData))->execute($request->model);
        }
    }

    /**
     * Resolve model status list
     * 
     * Note: Responses API shares the same models endpoint as Chat Completions
     * Model compatibility should be managed at the configuration level
     * 
     * @inheritDoc
     */
    protected function resolveStatusList(AiModelStatusCollection $statusCollection): void
    {
        // Responses API uses same models endpoint as OpenAI
        // The models are filtered in the model configuration
        // No special status request needed
    }
}
