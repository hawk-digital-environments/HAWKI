<?php
declare(strict_types=1);


namespace App\Services\AI\Providers;


use App\Services\AI\Exception\IncorrectClientForRequestedModelException;
use App\Services\AI\Exception\NoModelSetInRequestException;
use App\Services\AI\Interfaces\ClientInterface;
use App\Services\AI\Interfaces\ModelProviderInterface;
use App\Services\AI\Utils\ModelAwareClient;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiModelStatusCollection;
use App\Services\AI\Value\AiRequest;
use App\Services\AI\Value\AiResponse;
use App\Services\AI\Value\ModelOnlineStatus;

abstract class AbstractClient implements ClientInterface
{
    protected ModelProviderInterface $provider;
    protected ?AiModelStatusCollection $statusCollection = null;
    
    /**
     * @inheritDoc
     */
    public function setProvider(ModelProviderInterface $provider): void
    {
        $this->provider = $provider;
    }
    
    /**
     * @inheritDoc
     */
    final public function sendRequest(AiRequest $request): AiResponse
    {
        $this->validateRequest($request);
        
        return $this->executeRequest($request);
    }
    
    /**
     * Executed by {@see self::sendRequest()} after request validation. You can assume that the request is valid.
     * @param AiRequest $request
     * @return AiResponse
     */
    abstract protected function executeRequest(AiRequest $request): AiResponse;
    
    /**
     * @inheritDoc
     */
    final public function sendStreamRequest(AiRequest $request, callable $onData): void
    {
        $this->validateRequest($request);
        
        if (!$request->model->isStreamable()) {
            // If the model does not support streaming, fall back to regular request
            $response = $this->sendRequest($request);
            $onData($response);
            return;
        }
        
        $this->executeStreamingRequest($request, $onData);
    }
    
    /**
     * Executed by {@see self::sendStreamRequest()} after request validation. You can assume that the request is valid.
     * @param AiRequest $request
     * @param callable(AiResponse $response): void $onData Callback to process each chunk of data as it arrives
     * @return void
     */
    abstract protected function executeStreamingRequest(AiRequest $request, callable $onData): void;
    
    /**
     * @inheritDoc
     */
    final public function getStatus(AiModel $model): ModelOnlineStatus
    {
        if ($this->statusCollection === null) {
            $this->statusCollection = new AiModelStatusCollection($this->provider->getModels());
            $this->resolveStatusList($this->statusCollection);
        }
        
        return $this->statusCollection->getStatus($model);
    }
    
    /**
     * Fetch the status of all models from the provider and populate the given collection.
     * This method is called once per client instance when the status of any model is requested for the first time.
     * @param AiModelStatusCollection $statusCollection
     * @return void
     * @see AiModelStatusCollection for a usage example
     */
    abstract protected function resolveStatusList(AiModelStatusCollection $statusCollection): void;
    
    private function validateRequest(AiRequest $request): void
    {
        if ($request->model === null) {
            throw new NoModelSetInRequestException();
        }
        
        $modelClient = $request->model->getClient();
        if ($modelClient instanceof ModelAwareClient) {
            $modelClient = $modelClient->getConcreteClient();
        }
        
        if ($modelClient !== $this) {
            throw new IncorrectClientForRequestedModelException(
                $request->model->getClient(),
                $this
            );
        }
    }
}
