<?php
declare(strict_types=1);

namespace App\Services\AI\Providers\Anthropic;

use App\Services\AI\Providers\AbstractClient;
use App\Services\AI\Providers\Anthropic\Request\AnthropicModelStatusRequest;
use App\Services\AI\Providers\Anthropic\Request\AnthropicNonStreamingRequest;
use App\Services\AI\Providers\Anthropic\Request\AnthropicStreamingRequest;
use App\Services\AI\Value\AiModelStatusCollection;
use App\Services\AI\Value\AiRequest;
use App\Services\AI\Value\AiResponse;

class AnthropicClient extends AbstractClient
{
    public function __construct(
        private readonly AnthropicRequestConverter $converter
    )
    {
    }
    
    /**
     * @inheritDoc
     */
    protected function executeRequest(AiRequest $request): AiResponse
    {
        return (new AnthropicNonStreamingRequest(
            $this->converter->convertRequestToPayload($request)
        ))->execute($request->model);
    }
    
    /**
     * @inheritDoc
     */
    protected function executeStreamingRequest(AiRequest $request, callable $onData): void
    {
        (new AnthropicStreamingRequest(
            $this->converter->convertRequestToPayload($request),
            $onData
        ))->execute($request->model);
    }
    
    /**
     * @inheritDoc
     */
    protected function resolveStatusList(AiModelStatusCollection $statusCollection): void
    {
        (new AnthropicModelStatusRequest($this->provider))->execute($statusCollection);
    }
}
