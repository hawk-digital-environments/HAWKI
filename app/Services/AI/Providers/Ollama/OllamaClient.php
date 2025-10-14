<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\Ollama;


use App\Services\AI\Providers\AbstractClient;
use App\Services\AI\Providers\Ollama\Request\OllamaModelStatusRequest;
use App\Services\AI\Providers\Ollama\Request\OllamaNonStreamingRequest;
use App\Services\AI\Providers\Ollama\Request\OllamaStreamingRequest;
use App\Services\AI\Value\AiModelStatusCollection;
use App\Services\AI\Value\AiRequest;
use App\Services\AI\Value\AiResponse;

class OllamaClient extends AbstractClient
{
    public function __construct(
        private readonly OllamaRequestConverter $converter
    )
    {
    }

    /**
     * @inheritDoc
     */
    protected function executeRequest(AiRequest $request): AiResponse
    {
        return (new OllamaNonStreamingRequest($this->converter->convertRequestToPayload($request)))
            ->execute($request->model);
    }

    /**
     * @inheritDoc
     */
    protected function executeStreamingRequest(AiRequest $request, callable $onData): void
    {
        (new OllamaStreamingRequest($this->converter->convertRequestToPayload($request), $onData))
            ->execute($request->model);
    }

    /**
     * @inheritDoc
     */
    protected function resolveStatusList(AiModelStatusCollection $statusCollection): void
    {
        (new OllamaModelStatusRequest(($this->provider)))->execute($statusCollection);

    }

}
