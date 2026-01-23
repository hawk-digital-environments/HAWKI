<?php
declare(strict_types=1);


namespace App\Services\AI;


use App\Services\AI\Exception\ModelIdNotAvailableException;
use App\Services\AI\Exception\ModelNotInPayloadException;
use App\Services\AI\Exception\NoModelSetInRequestException;
use App\Services\AI\Tools\ToolExecutionService;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiRequest;
use App\Services\AI\Value\AiResponse;
use App\Services\AI\Value\AvailableAiModels;
use App\Services\AI\Value\ModelUsageType;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Log;

#[Singleton]
readonly class AiService
{
    public function __construct(
        private AiFactory $factory,
        private ToolExecutionService $toolExecutionService
    )
    {
    }

    /**
     * Get a list of all available models
     *
     * @param bool|null $external Set this to true, to receive the models enabled for external applications.
     *                           If null, the default models will be returned.
     */
    public function getAvailableModels(?bool $external = null): AvailableAiModels
    {
        $usageType = $external ? ModelUsageType::EXTERNAL_APP : ModelUsageType::DEFAULT;
        return $this->factory->getAvailableModels($usageType);
    }

    /**
     * Get a specific model by its ID
     *
     * @param string $modelId The model ID to retrieve
     * @param bool|null $external Set this to true, to receive the models enabled for external applications.
     *                           If null, the default models will be returned.
     * @return AiModel|null
     */
    public function getModel(string $modelId, ?bool $external = null): ?AiModel
    {
        return $this->getAvailableModels($external)->models->getModel($modelId);
    }

    /**
     * Get a specific model by its ID or throw an exception if not found
     *
     * @param string $modelId The model ID to retrieve
     * @param bool|null $external Set this to true, to receive the models enabled for external applications.
     *                           If null, the default models will be returned.
     * @return AiModel
     * @throws ModelIdNotAvailableException
     */
    public function getModelOrFail(string $modelId, ?bool $external = null): AiModel
    {
        $model = $this->getModel($modelId, $external);
        if (!$model) {
            throw new ModelIdNotAvailableException($modelId);
        }
        return $model;
    }

    /**
     * Sends an AI request to the appropriate model client.
     * The request will be handled without streaming; meaning the full response will be returned at once.
     * If the response contains tool calls, they will be automatically executed and a follow-up request will be sent.
     *
     * @param array|AiRequest $request Either an AiRequest object or an array representing the request payload.
     * @param int $maxToolRounds Maximum number of tool execution rounds to prevent infinite loops
     * @return AiResponse
     */
    public function sendRequest(array|AiRequest $request, int $maxToolRounds = 5): AiResponse
    {
        [$request, $model] = $this->resolveRequestAndModel($request);

        $round = 0;
        $currentRequest = $request;
        while (true) {
            $response = $model->getClient()->sendRequest($currentRequest);
            Log::debug($response);

            if (!$this->toolExecutionService->requiresToolExecution($response)) {
                return $response;
            }

            Log::info('Tool execution required', [
                'round' => $round + 1,
                'tool_count' => count($response->toolCalls),
            ]);

            $round++;

            // If we've reached max rounds, send final request without tools
            if ($round >= $maxToolRounds) {
                Log::warning('Max tool execution rounds reached', ['max_rounds' => $maxToolRounds]);

                $currentRequest = $this->toolExecutionService->buildFollowUpRequest(
                    $currentRequest,
                    $response,
                    disableTools: true
                );

                return $model->getClient()->sendRequest($currentRequest);
            }

            // Build follow-up request with tool results
            $currentRequest = $this->toolExecutionService->buildFollowUpRequest(
                $currentRequest,
                $response
            );
        }
    }

    /**
     * Sends an AI request to the appropriate model client with streaming support.
     * The response will be delivered in chunks via the provided callback function.
     * If the response contains tool calls, they will be automatically executed and a follow-up request will be sent.
     *
     * @param array|AiRequest $request Either an AiRequest object or an array representing the request payload.
     * @param callable(AiResponse $response): void $onData A callback function that will be called with each chunk of data received.
     *                         The function should accept a single parameter of type AiResponse.
     * @param int $maxToolRounds Maximum number of tool execution rounds to prevent infinite loops
     * @return void
     */
    public function sendStreamRequest(array|AiRequest $request, callable $onData, int $maxToolRounds = 5): void
    {
        [$request, $model] = $this->resolveRequestAndModel($request);

        $round = 0;
        $currentRequest = $request;
        $lastCompleteResponse = null;

        while (true) {
            // Wrap onData to capture the final complete response and mask tool call completion
            $wrappedOnData = function(AiResponse $response) use ($onData, &$lastCompleteResponse) {
                // If this is a tool call completion, don't tell the frontend it's done yet
                // We'll continue with follow-up requests
                if ($response->isDone && $response->finishReason === 'tool_calls') {
                    // Create a modified response with isDone=false for the frontend
                    $frontendResponse = new AiResponse(
                        content: $response->content,
                        usage: $response->usage,
                        isDone: false, // Mask the completion
                        error: $response->error,
                        toolCalls: $response->toolCalls,
                        finishReason: $response->finishReason,
                        type: $response->type,
                        statusMessage: $response->statusMessage
                    );
                    $onData($frontendResponse);
                    $lastCompleteResponse = $response; // Keep the real response internally
                } else {
                    // Normal response or final completion - send as is
                    $onData($response);
                    if ($response->isDone) {
                        $lastCompleteResponse = $response;
                    }
                }
            };

            // Send the streaming request
            $model->getClient()->sendStreamRequest($currentRequest, $wrappedOnData);

            // Check if tool execution is needed
            if (!$lastCompleteResponse || !$this->toolExecutionService->requiresToolExecution($lastCompleteResponse)) {
                // No tools needed or response complete, we're done
                return;
            }

            Log::info('Tool execution required in stream', [
                'round' => $round + 1,
                'tool_count' => count($lastCompleteResponse->toolCalls),
            ]);

            $round++;

            // Check if we've reached max rounds
            if ($round >= $maxToolRounds) {
                Log::warning('Max tool execution rounds reached in stream', ['max_rounds' => $maxToolRounds]);

                // Send status about max rounds
                $onData(
                    new AiResponse(
                        content: ['text' => ''],
                        isDone: false,
                        type: 'status',
                        statusMessage: 'Maximum tool execution rounds reached. Generating final response...'
                ));

                // Build final request with tools disabled
                $currentRequest = $this->toolExecutionService->buildFollowUpRequest(
                    $currentRequest,
                    $lastCompleteResponse,
                    disableTools: true
                );

                Log::info('Sending final request without tools', [
                    'message_count' => count($currentRequest->payload['messages']),
                    'has_tools' => isset($currentRequest->payload['tools']),
                ]);

                // Send the final request directly and return
                $model->getClient()->sendStreamRequest($currentRequest, $onData);

                Log::info('Final request completed');
                return;
            }

            // Send status message about tool execution
            $toolNames = array_map(fn($tc) => $tc->name, $lastCompleteResponse->toolCalls);
            $statusMsg = 'Executing ' . implode(', ', $toolNames) . '...';
            $onData(new AiResponse(
                content: ['text' => ''],
                isDone: false,
                type: 'status',
                statusMessage: $statusMsg
            ));

            // Build follow-up request with tool results
            $currentRequest = $this->toolExecutionService->buildFollowUpRequest(
                $currentRequest,
                $lastCompleteResponse
            );

            // Note: Don't reset $lastCompleteResponse to null here
            // It will be overwritten in the next iteration when isDone=true
        }
    }

    /**
     * Helper to resolve the request and model object based on the provided input.
     * @param array|AiRequest $request
     * @return array{0: AiRequest, 1: AiModel}
     */
    private function resolveRequestAndModel(array|AiRequest $request): array
    {
        if (is_array($request)) {
            $modelId = $request['model'] ?? null;
            if (empty($modelId)) {
                throw new ModelNotInPayloadException($request);
            }
            $model = $this->getModelOrFail($modelId);
            $request = new AiRequest(payload: $request);
            return [$request, $model];
        }

        if ($request->model === null) {
            throw new NoModelSetInRequestException();
        }

        return [$request, $request->model];
    }
}
