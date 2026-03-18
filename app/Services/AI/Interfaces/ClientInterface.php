<?php
declare(strict_types=1);


namespace App\Services\AI\Interfaces;


use App\Services\AI\AiFactory;
use App\Services\AI\Utils\ClientStack;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiRequest;
use App\Services\AI\Value\AiResponse;
use App\Services\AI\Value\ModelOnlineStatus;

interface ClientInterface
{
    /**
     * Builds the client stack by collecting all clients in the chain.
     * Each client can choose to add itself and/or child clients to the stack.
     * The final result describes the chain from the top-level client down to the concrete model client,
     * and is used for dedicated instance lookup in a nested call context.
     * @param ClientStack $stack
     * @return ClientStack
     * @internal
     */
    public function buildStack(ClientStack $stack): ClientStack;

    /**
     * Injects the model provider to be used by the client.
     * This method is intended for internal use only and will be called by {@see AiFactory::getClientForProvider()}
     * @param ModelProviderInterface $provider
     * @return void
     * @internal
     */
    public function setProvider(ModelProviderInterface $provider): void;

    /**
     * Sends a non-streaming request to the AI service.
     * The execution is synchronous and will wait for the full response.
     * @param AiRequest $request
     * @return AiResponse
     */
    public function sendRequest(AiRequest $request): AiResponse;

    /**
     * Sends a streaming request to the AI service.
     * The execution is asynchronous and will invoke the $onData callback for each chunk of data received.
     * Track the {@see AiResponse::$isDone} property to determine when the stream is complete.
     * @param AiRequest $request
     * @param callable(AiResponse): void $onData
     * @return void
     */
    public function sendStreamRequest(AiRequest $request, callable $onData): void;

    /**
     * Sends a status check request for the given model.
     *
     * @param AiModel $model
     * @return ModelOnlineStatus
     */
    public function getStatus(AiModel $model): ModelOnlineStatus;
}
