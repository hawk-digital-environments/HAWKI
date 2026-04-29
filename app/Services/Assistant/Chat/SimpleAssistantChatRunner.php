<?php

declare(strict_types=1);

namespace App\Services\Assistant\Chat;

use App\Services\AI\AiService;
use App\Services\AI\Value\AiResponse;
use Generator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SimpleAssistantChatRunner implements AssistantChatRunnerInterface
{
    public function __construct(
        private readonly AiService $aiService,
    ) {}

    public function stream(
        string $systemPrompt,
        array $messages,
        string $model,
        array $tools = [],
        array $params = [],
        ?callable $sink = null,
    ): Generator {
        $payload = $this->buildPayload($systemPrompt, $messages, $model, $tools, $params);
        $chunks = [];

        $addChunk = static function (array $chunk) use (&$chunks, $sink): void {
            $chunks[] = $chunk;
            if ($sink !== null) {
                $sink($chunk);
            }
        };

        $onData = function (AiResponse $response) use (&$chunks, $addChunk): void {
            if ($response->error !== null) {
                throw new \RuntimeException('AI provider error: '.($response->content['error'] ?? $response->error));
            }

            if ($response->type === 'thinking') {
                $addChunk([
                    'type' => 'thinking_delta',
                    'content' => $response->content['text'] ?? '',
                ]);

                return;
            }

            if ($response->type === 'tool_call' && ! empty($response->content['tool_call_id'] ?? null)) {
                $addChunk([
                    'type' => 'tool_call',
                    'content' => [
                        'tool_id' => $response->content['tool_call_id'],
                        'tool_name' => $response->content['tool_name'] ?? '',
                        'arguments' => $response->content['arguments'] ?? [],
                    ],
                ]);

                return;
            }

            if ($response->type === 'tool_result') {
                $addChunk([
                    'type' => 'tool_result',
                    'content' => [
                        'tool_id' => $response->content['tool_call_id'] ?? '',
                        'tool_name' => $response->content['tool_name'] ?? '',
                        'result' => $response->content['result'] ?? null,
                    ],
                ]);

                return;
            }

            if ($response->type === 'status') {
                $addChunk([
                    'type' => 'status',
                    'content' => $response->status ?? $response->content,
                ]);

                return;
            }

            if ($response->usage !== null) {
                $addChunk([
                    'type' => 'usage',
                    'content' => [
                        'prompt_tokens' => $response->usage->promptTokens,
                        'completion_tokens' => $response->usage->completionTokens,
                    ],
                ]);
            }

            if (! empty($response->content['text'] ?? null)) {
                $addChunk([
                    'type' => 'text_delta',
                    'content' => $response->content['text'],
                ]);
            }
        };

        $this->aiService->sendStreamRequest($payload, $onData);

        if ($chunks === []) {
            Log::debug('chatTest: 0 chunks, fallback', [
                'model' => $model ?? ($payload['model'] ?? '?'),
                'has_no_tool_execution' => ! empty($payload['params']['_no_tool_execution'] ?? null),
            ]);
            $response = $this->aiService->sendRequest($payload);

            Log::debug('chatTest: fallback response', [
                'type' => $response->type,
                'content_keys' => array_keys($response->content),
                'content' => Str::limit(json_encode($response->content), 300),
                'has_text' => ! empty($response->content['text'] ?? null),
                'finishReason' => $response->finishReason,
            ]);

            if (! empty($response->content['text'] ?? null)) {
                $addChunk([
                    'type' => 'text_delta',
                    'content' => $response->content['text'],
                ]);
            }
        }

        Log::debug('chatTest: chunks collected', [
            'model' => $model,
            'count' => count($chunks),
            'types' => array_column($chunks, 'type'),
        ]);

        foreach ($chunks as $chunk) {
            yield $chunk;
        }
    }

    private function buildPayload(
        string $systemPrompt,
        array $messages,
        string $model,
        array $tools,
        array $params,
    ): array {
        $payload = [
            'model' => $model,
            'stream' => true,
            'messages' => [],
        ];

        if ($systemPrompt !== '') {
            $payload['messages'][] = [
                'role' => 'system',
                'content' => ['text' => $systemPrompt],
            ];
        }

        foreach ($messages as $message) {
            $payload['messages'][] = [
                'role' => $message['role'],
                'content' => $message['content'] ?? ['text' => ''],
            ];
        }

        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        if ($params !== []) {
            $payload['params'] = $params;
        }

        return $payload;
    }
}
