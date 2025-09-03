<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\Gwdg;


use App\Services\AI\Providers\AbstractClient;
use App\Services\AI\Providers\Gwdg\Request\GwdgNonStreamingRequest;
use App\Services\AI\Providers\Gwdg\Request\GwdgStreamingRequest;
use App\Services\AI\Value\AiModelStatusCollection;
use App\Services\AI\Value\AiRequest;
use App\Services\AI\Value\AiResponse;

class GwdgClient extends AbstractClient
{
    public function __construct(
        private readonly GwdgRequestConverter $requestConverter
    )
    {
    }
    
    /**
     * @inheritDoc
     */
    protected function executeRequest(AiRequest $request): AiResponse
    {
        return (new GwdgNonStreamingRequest(
            $this->requestConverter->convertRequestToPayload($request)
        ))->execute($request->model);
    }
    
    /**
     * @inheritDoc
     */
    protected function executeStreamingRequest(AiRequest $request, callable $onData): void
    {
        (new GwdgStreamingRequest(
            $this->requestConverter->convertRequestToPayload($request),
            $onData
        ))->execute($request->model);
    }
    
    /**
     * @inheritDoc
     */
    protected function resolveStatusList(AiModelStatusCollection $statusCollection): void
    {
        // @todo implement model status check for GWDG
        $statusCollection->setAllOnline();
    }
}
