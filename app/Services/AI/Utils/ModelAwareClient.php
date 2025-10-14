<?php
declare(strict_types=1);


namespace App\Services\AI\Utils;


use App\Services\AI\Interfaces\ClientInterface;
use App\Services\AI\Interfaces\ModelProviderInterface;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiRequest;
use App\Services\AI\Value\AiResponse;
use App\Services\AI\Value\ModelOnlineStatus;

/**
 * A client wrapper that ensures a specific model is always set in requests.
 */
readonly class ModelAwareClient implements ClientInterface
{
    public function __construct(
        private ClientInterface $concreteClient,
        private AiModel         $model
    )
    {
    }
    
    public function getConcreteClient(): ClientInterface
    {
        return $this->concreteClient;
    }
    
    /**
     * @inheritDoc
     */
    public function setProvider(ModelProviderInterface $provider): void
    {
        $this->concreteClient->setProvider($provider);
    }
    
    /**
     * @inheritDoc
     */
    public function sendStreamRequest(AiRequest $request, callable $onData): void
    {
        if ($request->model === null) {
            $request = $request->withModel($this->model);
        }
        $this->concreteClient->sendStreamRequest($request, $onData);
    }
    
    /**
     * @inheritDoc
     */
    public function sendRequest(AiRequest $request): AiResponse
    {
        if ($request->model === null) {
            $request = $request->withModel($this->model);
        }
        return $this->concreteClient->sendRequest($request);
    }
    
    /**
     * @inheritDoc
     */
    public function getStatus(?AiModel $model = null): ModelOnlineStatus
    {
        return $this->concreteClient->getStatus($model ?? $this->model);
    }
}
