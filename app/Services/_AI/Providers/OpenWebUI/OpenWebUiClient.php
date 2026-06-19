<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\OpenWebUI;


use App\Services\AI\Providers\AbstractClient;
use App\Services\AI\Providers\OpenWebUI\Request\OpenWebUiNonStreamingRequest;
use App\Services\AI\Value\AiModelStatusCollection;
use App\Services\AI\Value\AiRequest;
use App\Services\AI\Value\AiResponse;

class OpenWebUiClient extends AbstractClient
{
    public function __construct(
        private readonly OpenWebUiRequestConverter $requestConverter
    )
    {
    }
    
    /**
     * @inheritDoc
     */
    protected function executeRequest(AiRequest $request): AiResponse
    {
        return (new OpenWebUiNonStreamingRequest($this->requestConverter->convertRequestToPayload($request)))
            ->execute($request->model);
    }
    
    /**
     * @inheritDoc
     */
    protected function executeStreamingRequest(AiRequest $request, callable $onData): void
    {
        (new OpenWebUiNonStreamingRequest($this->requestConverter->convertRequestToPayload($request)))
            ->execute($request->model);
    }
    
    /**
     * @inheritDoc
     */
    protected function resolveStatusList(AiModelStatusCollection $statusCollection): void
    {
        // @todo implement model status check for OpenWebUi
        $statusCollection->setAllOnline();
    }
    
}
