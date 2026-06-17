<?php

declare(strict_types=1);

namespace App\Services\Assistant\Chat;

use App\Services\AI\AiService;
use App\Services\AI\Value\AiResponse;
use Generator;

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
    ): Generator {
        $payload = $this->buildPayload($systemPrompt, $messages, $model, $tools, $params);
        $chunks = [];

        $onData = function (AiResponse $response) use (&$chunks): void {
            if ($response->error !== null) {
                throw new \RuntimeException('AI provider error: '.($response->content['error'] ?? $response->error));
            }

            if ($response->type === 'tool_call' && ! empty($response->content['tool_call_id'] ?? null)) {
                $chunks[] = [
                    'type' => 'tool_call',
                    'content' => [
                        'tool_id' => $response->content['tool_call_id'],
                        'tool_name' => $response->content['tool_name'] ?? '',
                        'arguments' => $response->content['arguments'] ?? [],
                    ],
                ];

                return;
            }

            if ($response->type === 'tool_result') {
                $chunks[] = [
                    'type' => 'tool_result',
                    'content' => [
                        'tool_id' => $response->content['tool_call_id'] ?? '',
                        'tool_name' => $response->content['tool_name'] ?? '',
                        'result' => $response->content['result'] ?? null,
                    ],
                ];

                return;
            }

            if ($response->type === 'status') {
                $chunks[] = [
                    'type' => 'status',
                    'content' => $response->status ?? $response->content,
                ];

                return;
            }

            if ($response->usage !== null) {
                $chunks[] = [
                    'type' => 'usage',
                    'content' => [
                        'prompt_tokens' => $response->usage->promptTokens,
                        'completion_tokens' => $response->usage->completionTokens,
                    ],
                ];
            }

            if (! empty($response->content['text'] ?? null)) {
                $chunks[] = [
                    'type' => 'text_delta',
                    'content' => $response->content['text'],
                ];
            }
        };

        $this->aiService->sendStreamRequest($payload, $onData);

        if ($chunks === []) {
            $response = $this->aiService->sendRequest($payload);

            if (! empty($response->content['text'] ?? null)) {
                $chunks[] = [
                    'type' => 'text_delta',
                    'content' => $response->content['text'],
                ];
            }
        }

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
