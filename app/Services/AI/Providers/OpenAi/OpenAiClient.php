<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\OpenAi;


use App\Services\AI\Providers\AbstractClient;
use App\Services\AI\Providers\OpenAi\Request\OpenAiModelStatusRequest;
use App\Services\AI\Providers\OpenAi\Request\OpenAiNonStreamingRequest;
use App\Services\AI\Providers\OpenAi\Request\OpenAiStreamingRequest;
use App\Services\AI\Value\AiModelStatusCollection;
use App\Services\AI\Value\AiRequest;
use App\Services\AI\Value\AiResponse;

class OpenAiClient extends AbstractClient
{
    public function __construct(
        private readonly OpenAiRequestConverter $converter
    )
    {
    }
    
    /**
     * @inheritDoc
     */
    protected function executeRequest(AiRequest $request): AiResponse
    {
        return (new OpenAiNonStreamingRequest(
            $this->converter->convertRequestToPayload($request)
        ))->execute($request->model);
    }
    
    /**
     * @inheritDoc
     */
    protected function executeStreamingRequest(AiRequest $request, callable $onData): void
    {
        (new OpenAiStreamingRequest(
            $this->converter->convertRequestToPayload($request),
            $onData
        ))->execute($request->model);
    }
    
    /**
     * @inheritDoc
     */
    protected function resolveStatusList(AiModelStatusCollection $statusCollection): void
    {
        (new OpenAiModelStatusRequest($this->provider))->execute($statusCollection);
    }
}
