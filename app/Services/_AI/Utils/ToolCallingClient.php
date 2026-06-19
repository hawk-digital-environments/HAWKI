<?php
declare(strict_types=1);


namespace App\Services\AI\Utils;


use App\Services\AI\Interfaces\ClientInterface;
use App\Services\AI\Interfaces\ModelProviderInterface;
use App\Services\AI\Tools\ToolRegistry;
use App\Services\AI\Value\AiErrorResponse;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiRequest;
use App\Services\AI\Value\AiResponse;
use App\Services\AI\Value\MaxToolExecutionRoundsResponse;
use App\Services\AI\Value\ModelOnlineStatus;
use App\Services\AI\Value\ToolCallAiResponse;
use App\Services\AI\Value\ToolCallFollowUpAiRequest;
use App\Services\AI\Value\ToolCallStatusResponse;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * A Client decorator that adds support for AiTool calling based on the model's capabilities. It
 * intercepts responses from the underlying model client, detects when tool calls are required, executes the tools using
 * the ToolRegistry, and sends follow-up requests with the tool results until a final response without
 * tool calls is received or the maximum number of tool calling rounds is reached.
 */
readonly class ToolCallingClient implements ClientInterface
{
    public function __construct(
        private ClientInterface $modelClient,
        private ToolRegistry    $toolRegistry,
        private LoggerInterface $logger
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function buildStack(ClientStack $stack): ClientStack
    {
        return $stack->push($this, $this->modelClient);
    }

    /**
     * @inheritDoc
     */
    public function setProvider(ModelProviderInterface $provider): void
    {
        $this->modelClient->setProvider($provider);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(AiModel $model): ModelOnlineStatus
    {
        return $this->modelClient->getStatus($model);
    }

    /**
     * @inheritDoc
     */
    public function sendRequest(AiRequest $request): AiResponse
    {
        // If no model is set, we cannot determine tool calling capabilities,
        // so we just pass through the request to the underlying client without any tool calling support.
        if ($request->model === null) {
            $this->logger->warning('Received AiRequest without a model, skipping tool calling logic');
            return $this->modelClient->sendRequest($request);
        }

        $maxToolRounds = $request->model->getMaxToolCallingRounds(false);
        $round = 0;
        $currentRequest = $request;

        while (true) {
            $response = $this->modelClient->sendRequest($currentRequest);

            if (!$response instanceof ToolCallAiResponse) {
                return $response;
            }

            // Security, if the model keeps calling tools for some reason,
            // we need to break the loop at some point to avoid infinite loops and potential abuse.
            // This triggers ONLY, if the model responds with a tool calls,
            // even if the follow-up request is marked as not allowing further tool calls, which should never happen,
            // but we want to be safe.
            if ($round >= $maxToolRounds) {
                return new AiErrorResponse('Maximum tool calling rounds reached, aborting to prevent potential infinite loop');
            }

            $round++;

            Log::info('AiTool execution required', [
                'round' => $round,
                'tool_count' => count($response->toolCalls),
            ]);

            $currentRequest = ToolCallFollowUpAiRequest::fromRequest(
                originalRequest: $request,
                responseThatCallsTools: $response,
                toolResults: $response->toolCalls->execute($this->toolRegistry),
                allowsFurtherToolCalls: $round < $maxToolRounds
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function sendStreamRequest(AiRequest $request, callable $onData): void
    {
        // If no model is set, we cannot determine tool calling capabilities,
        // so we just pass through the request to the underlying client without any tool calling support.
        if ($request->model === null) {
            $this->logger->warning('Received AiRequest without a model, skipping tool calling logic');
            $this->modelClient->sendStreamRequest($request, $onData);
            return;
        }

        $maxToolRounds = $request->model->getMaxToolCallingRounds(true);
        $round = 0;
        $currentRequest = $request;

        while (true) {
            $lastCompleteResponse = null;

            $this->modelClient->sendStreamRequest(
                $currentRequest,
                /**
                 * Wrap around the onData callback to intercept streaming responses and detect when a complete response with
                 * tool calls is received. This allows us to execute the tool calls and send follow-up requests without
                 * losing the streaming nature of the response.
                 */
                static function (AiResponse|ToolCallAiResponse $response) use ($onData, &$lastCompleteResponse) {
                    if ($response instanceof ToolCallAiResponse) {
                        $onData($response->toStreamUpdateResponse());
                    } else {
                        $onData($response);
                    }

                    if ($response->isDone) {
                        $lastCompleteResponse = $response;
                    }
                }
            );

            // If the last complete response does not contain tool calls, we are done
            if (!$lastCompleteResponse instanceof ToolCallAiResponse) {
                return;
            }

            // Security, if the model keeps calling tools for some reason,
            // we need to break the loop at some point to avoid infinite loops and potential abuse.
            // This triggers ONLY, if the model responds with a tool calls,
            // even if the follow-up request is marked as not allowing further tool calls, which should never happen,
            // but we want to be safe.
            if ($round >= $maxToolRounds) {
                $onData(new AiErrorResponse('Maximum tool calling rounds reached, aborting to prevent potential infinite loop'));
                return;
            }

            $round++;

            // Check if we've reached max rounds
            if ($round >= $maxToolRounds) {
                // Send status about max rounds
                $onData(new MaxToolExecutionRoundsResponse());
            } else {
                // Send status about tool execution
                $onData(ToolCallStatusResponse::fromToolCallsAndRegistry(
                    $lastCompleteResponse->toolCalls,
                    $this->toolRegistry
                ));
            }

            // Build follow-up request with tool results
            $currentRequest = ToolCallFollowUpAiRequest::fromRequest(
                originalRequest: $request,
                responseThatCallsTools: $lastCompleteResponse,
                toolResults: $lastCompleteResponse->toolCalls->execute($this->toolRegistry),
                allowsFurtherToolCalls: $round < $maxToolRounds
            );
        }
    }
}
