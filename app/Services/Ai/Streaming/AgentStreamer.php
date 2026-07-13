<?php

declare(strict_types=1);

namespace App\Services\Ai\Streaming;

use App\Services\Ai\AiService;
use Laravel\Ai\Streaming\Events\Error;
use Laravel\Ai\Streaming\Events\ReasoningDelta;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;

/**
 * Streams a chat exchange by driving the upstream laravel/ai agent pipeline
 * (AiService -> AgentRegistry -> ChatAgent -> sendStreaming()) and re-emitting
 * its chunks in the {type, content} shape the SSE adapters expect.
 *
 * The legacy request payload shape understood by ChatAgentFromLegacyRequestFactory
 * is assembled from the streamer inputs (system prompt becomes the system message;
 * the first system message is extracted as the agent's instructions).
 */
class AgentStreamer implements AgentStreamerInterface
{
    public function __construct(private readonly AiService $aiService)
    {
    }

    public function stream(
        string $systemPrompt,
        array $messages,
        string $model,
        array $tools = [],
        array $params = [],
        ?callable $sink = null,
    ): \Generator {
        $payload = [
            'payload' => [
                'model' => $model,
                'messages' => array_merge(
                    [['role' => 'system', 'content' => ['text' => $systemPrompt]]],
                    $messages,
                ),
                'params' => $params,
                'tools' => array_values($tools),
            ],
        ];

        $agent = $this->aiService->getAgent($payload);
        $response = $agent->sendStreaming();

        foreach ($response as $chunk) {
            $parsed = match (true) {
                $chunk instanceof TextDelta => ['type' => 'text_delta', 'content' => $chunk->delta],
                $chunk instanceof ReasoningDelta => ['type' => 'thinking_delta', 'content' => $chunk->delta],
                $chunk instanceof ToolCall => [
                    'type' => 'tool_call',
                    'content' => [
                        'tool_id' => $chunk->toolCall->id,
                        'tool_name' => $chunk->toolCall->name,
                        'arguments' => $chunk->toolCall->arguments,
                    ],
                ],
                $chunk instanceof ToolResult => [
                    'type' => 'tool_result',
                    'content' => [
                        'tool_id' => $chunk->toolResult->id,
                        'tool_name' => $chunk->toolResult->name,
                        'result' => $chunk->toolResult->result,
                    ],
                ],
                $chunk instanceof StreamEnd => [
                    'type' => 'usage',
                    'content' => [
                        'prompt_tokens' => $chunk->usage->promptTokens,
                        'completion_tokens' => $chunk->usage->completionTokens,
                    ],
                ],
                $chunk instanceof Error => throw new \RuntimeException($chunk->message),
                default => null,
            };

            if (null === $parsed) {
                continue;
            }

            if (null !== $sink) {
                $sink($parsed);
            }

            yield $parsed;
        }
    }
}
