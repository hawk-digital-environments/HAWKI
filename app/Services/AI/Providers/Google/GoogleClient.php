<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\Google;


use App\Services\AI\Providers\AbstractClient;
use App\Services\AI\Providers\Google\Request\GoogleModelStatusRequest;
use App\Services\AI\Providers\Google\Request\GoogleNonStreamingRequest;
use App\Services\AI\Providers\Google\Request\GoogleStreamingRequest;
use App\Services\AI\Value\AiModelStatusCollection;
use App\Services\AI\Value\AiRequest;
use App\Services\AI\Value\AiResponse;

class GoogleClient extends AbstractClient
{
    public function __construct(
        private readonly GoogleRequestConverter $requestConverter
    )
    {
    }
    
    /**
     * @inheritDoc
     */
    protected function executeRequest(AiRequest $request): AiResponse
    {
        return (new GoogleNonStreamingRequest(
            $this->requestConverter->convertRequestToPayload($request)
        ))->execute($request->model);
    }
    
    /**
     * @inheritDoc
     */
    protected function executeStreamingRequest(AiRequest $request, callable $onData): void
    {
        (new GoogleStreamingRequest(
            $this->requestConverter->convertRequestToPayload($request),
            $onData
        ))->execute($request->model);
    }
    
    /**
     * @inheritDoc
     */
    protected function resolveStatusList(AiModelStatusCollection $statusCollection): void
    {
        (new GoogleModelStatusRequest($this->provider))->execute($statusCollection);
    }
}
