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
     * @inheritDoc
     */
    protected function executeStreamingRequest(AiRequest $request, callable $onData): void
    {
        // Validate model compatibility before making request
        if (!$this->isModelCompatible($request->model->getId())) {
            throw new \Exception(
                "Model '{$request->model->getId()}' is not compatible with Responses API. " .
                "Only GPT-4/5/6 families are supported (excluding o1/o3 models)."
            );
        }

        (new ResponsesStreamingRequest(
            $this->converter->convertRequestToPayload($request),
            $onData
        ))->execute($request->model);
    }

    /**
     * Resolve model status list
     * 
     * Note: Responses API shares the same models endpoint as Chat Completions
     * We filter for compatible models only
     * 
     * @inheritDoc
     */
    protected function resolveStatusList(AiModelStatusCollection $statusCollection): void
    {
        // Responses API uses same models endpoint as OpenAI
        // The models are filtered in the model configuration
        // No special status request needed
    }

    /**
     * Check if model is compatible with Responses API
     * 
     * Only GPT-4, GPT-5, and GPT-6 families are supported
     * Excludes o1 and o3 reasoning models (not compatible with Responses API)
     * 
     * @param string $modelId
     * @return bool
     */
    private function isModelCompatible(string $modelId): bool
    {
        $compatiblePrefixes = [
            'gpt-4',  // GPT-4 family
            'gpt-5',  // GPT-5 family
            'gpt-6',  // GPT-6 family (future)
        ];

        $incompatiblePrefixes = [
            'o1',     // o1 models NOT compatible
            'o3',     // o3 models NOT compatible
        ];

        // Check if model starts with incompatible prefix
        foreach ($incompatiblePrefixes as $prefix) {
            if (str_starts_with($modelId, $prefix)) {
                return false;
            }
        }

        // Check if model starts with compatible prefix
        foreach ($compatiblePrefixes as $prefix) {
            if (str_starts_with($modelId, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
