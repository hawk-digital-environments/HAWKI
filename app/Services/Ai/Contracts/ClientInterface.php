<?php
declare(strict_types=1);


namespace App\Services\Ai\Contracts;


use App\Models\Ai\AiModel;
use App\Services\Ai\Clients\Requests\AiRequest;
use App\Services\Ai\Clients\Responses\AiResponse;
use App\Services\Ai\Values\OnlineStatus;

interface ClientInterface
{
    /**
     * If this client decorates another client, this method should return the inner client. Otherwise, it should return null.
     * @return ClientInterface|null
     */
    public function getInnerClient(): ClientInterface|null;

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
     * @return OnlineStatus
     */
    public function getStatus(AiModel $model): OnlineStatus;
}
