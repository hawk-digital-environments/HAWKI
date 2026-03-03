<?php
declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Services\AI\Tools\Value\ToolCall;
use App\Services\AI\Tools\Value\ToolResult;
use App\Services\AI\Value\AiRequest;
use App\Services\AI\Value\AiResponse;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Log;

#[Singleton]
class ToolExecutionService
{
    public function __construct(
        private ToolRegistry $toolRegistry
    )
    {
    }

    /**
     * Execute an array of tool calls
     *
     * @param array<ToolCall> $toolCalls
     * @return array<ToolResult>
     */
    public function executeToolCalls(array $toolCalls): array
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            $result = $this->toolRegistry->execute(
                toolName: $toolCall->name,
                arguments: $toolCall->arguments,
                toolCallId: $toolCall->id
            );

            $results[] = $result;

            if ($result->success) {
                Log::info('AiTool executed successfully', [
                    'tool' => $toolCall->name,
                    'tool_call_id' => $toolCall->id,
                ]);
            } else {
                Log::error('AiTool execution failed', [
                    'tool' => $toolCall->name,
                    'tool_call_id' => $toolCall->id,
                    'error' => $result->error,
                ]);
            }
        }

        return $results;
    }

    /**
     * Build a follow-up request with tool results
     *
     * This creates a new request that includes:
     * 1. The original messages
     * 2. An assistant message with the tool calls
     * 3. AiTool result messages
     *
     * @param AiRequest $originalRequest The original request that triggered tool calls
     * @param AiResponse $toolResponse The response containing tool calls
     * @param bool $disableTools Whether to disable tools in the follow-up request
     * @return AiRequest A new request with tool results
     */
    public function buildFollowUpRequest(
        AiRequest $originalRequest,
        AiResponse $toolResponse,
        bool $disableTools = false
    ): AiRequest {
        $payload = $originalRequest->payload;

        // Add the assistant message with tool calls
        $payload['messages'][] = [
            'role' => 'assistant',
            'content' => $toolResponse->content['text'] ?? null,
            'tool_calls' => array_map(
                fn(ToolCall $tc) => $tc->jsonSerialize(),
                $toolResponse->toolCalls
            ),
        ];

        // Execute tools and add results as messages
        $toolResults = $this->executeToolCalls($toolResponse->toolCalls);

//        Log::info('Building follow-up request with tool results', [
//            'tool_results_count' => count($toolResults),
//            'messages_before' => count($payload['messages']),
//        ]);

        foreach ($toolResults as $result) {
            $messageFormat = $result->toMessageFormat();
            $payload['messages'][] = $messageFormat;

//            Log::info('Added tool result message', [
//                'role' => $messageFormat['role'],
//                'tool_call_id' => $messageFormat['tool_call_id'],
//                'content_length' => strlen($messageFormat['content']),
//                'content_preview' => substr($messageFormat['content'], 0, 200),
//            ]);
        }

        // Mark to disable tools if requested
        if ($disableTools) {
            $payload['_disable_tools'] = true;
            $payload['tools'] = [];
        }

        Log::info('Follow-up request built', [
            'total_messages' => count($payload['messages']),
            'has_tools' => isset($payload['tools']) && !empty($payload['tools']),
            'last_3_message_roles' => array_slice(array_column($payload['messages'], 'role'), -3),
        ]);

        return new AiRequest(
            model: $originalRequest->model,
            payload: $payload
        );
    }

    /**
     * Check if a response requires tool execution
     */
    public function requiresToolExecution(AiResponse $response): bool
    {
        return $response->hasToolCalls() && $response->isDone;
    }

    /**
     * Convert tool results to message format for the API
     *
     * @param array<ToolResult> $results
     * @return array
     */
    public function toolResultsToMessages(array $results): array
    {
        return array_map(
            fn(ToolResult $result) => $result->toMessageFormat(),
            $results
        );
    }
}
