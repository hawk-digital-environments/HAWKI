<?php

declare(strict_types=1);

namespace App\Services\Ai\Streaming\Sse;

/**
 * Implementation according to spec:
 * https://github.com/openai/openai-openapi
 * For a refernce implementation see https://github.com/vercel/ai/blob/83877a1e/packages/openai/src/responses/openai-responses-language-model.ts#L1163
 */
class OpenAIResponsesAdapter extends SSEAdapter
{
    private int $seq = 0;
    private int $outputIndex = 0;
    private ?string $msgItemId = null;
    private bool $inMessage = false;
    private string $fullContent = '';
    private array $outputItems = [];
    private array $usage = ['input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0];
    private int $createdAt;
    private bool $inThinking = false;
    private ?string $thinkingItemId = null;
    private string $fullThinkingText = '';

    public function __construct(
        private readonly string $responseId,
        private readonly string $model,
        ?int $createdAt = null,
    ) {
        $this->createdAt = $createdAt ?? now()->timestamp;
    }

    public function start(): iterable
    {
        $responseObject = $this->buildResponseObject('in_progress', []);

        yield $this->formatEvent('response.created', [
            'response' => $responseObject,
            'sequence_number' => $this->nextSeq(),
        ]);

        yield $this->formatEvent('response.in_progress', [
            'response' => $responseObject,
            'sequence_number' => $this->nextSeq(),
        ]);
    }

    public function transform(array $chunk): iterable
    {
        yield from match ($chunk['type']) {
            'text_delta' => $this->handleTextDelta($chunk['content']),
            'thinking_delta' => $this->handleThinkingDelta($chunk['content']),
            'tool_call' => $this->handleToolCall($chunk['content']),
            'tool_result' => $this->handleToolResult($chunk['content']),
            'usage' => $this->handleUsage($chunk['content']),
            'status' => [],
            default => [],
        };
    }

    public function end(): iterable
    {
        if ($this->inThinking) {
            yield from $this->closeThinking();
        }

        if ($this->inMessage) {
            yield from $this->closeMessageItem();
        }

        $responseObject = $this->buildResponseObject('completed', $this->outputItems, $this->usage);

        yield $this->formatEvent('response.completed', [
            'response' => $responseObject,
            'sequence_number' => $this->nextSeq(),
        ]);
    }

    public function error(\Throwable $e): iterable
    {
        yield $this->formatEvent('error', [
            'code' => null,
            'message' => $e->getMessage(),
            'param' => null,
            'sequence_number' => $this->nextSeq(),
        ]);
    }

    private function nextSeq(): int
    {
        return $this->seq++;
    }

    private function buildResponseObject(string $status, array $output, ?array $usage = null): array
    {
        return [
            'id' => $this->responseId,
            'object' => 'response',
            'status' => $status,
            'model' => $this->model,
            'output' => $output,
            'usage' => $usage ?? $this->usage,
            'created_at' => $this->createdAt,
        ];
    }

    private function handleTextDelta(string $delta): iterable
    {
        if ($this->inThinking) {
            yield from $this->closeThinking();
        }

        if (!$this->inMessage) {
            $this->msgItemId = $this->generateId('msg');
            $this->inMessage = true;

            yield $this->formatEvent('response.output_item.added', [
                'output_index' => $this->outputIndex,
                'item' => [
                    'id' => $this->msgItemId,
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [],
                    'status' => 'in_progress',
                ],
                'sequence_number' => $this->nextSeq(),
            ]);

            yield $this->formatEvent('response.content_part.added', [
                'item_id' => $this->msgItemId,
                'output_index' => $this->outputIndex,
                'content_index' => 0,
                'part' => ['type' => 'output_text', 'text' => '', 'annotations' => []],
                'sequence_number' => $this->nextSeq(),
            ]);
        }

        yield $this->formatEvent('response.output_text.delta', [
            'item_id' => $this->msgItemId,
            'output_index' => $this->outputIndex,
            'content_index' => 0,
            'delta' => $delta,
            'sequence_number' => $this->nextSeq(),
        ]);

        $this->fullContent .= $delta;
    }

    private function handleToolCall(array $content): iterable
    {
        if ($this->inThinking) {
            yield from $this->closeThinking();
        }

        if ($this->inMessage) {
            yield from $this->closeMessageItem();
        }

        $fcItemId = $this->generateId('fc');
        $argumentsJson = \is_string($content['arguments'])
            ? $content['arguments']
            : json_encode($content['arguments'], \JSON_UNESCAPED_UNICODE);

        yield $this->formatEvent('response.output_item.added', [
            'output_index' => $this->outputIndex,
            'item' => [
                'id' => $fcItemId,
                'type' => 'function_call',
                'call_id' => $content['tool_id'],
                'name' => $content['tool_name'],
                'arguments' => '',
                'status' => 'in_progress',
            ],
            'sequence_number' => $this->nextSeq(),
        ]);

        yield $this->formatEvent('response.function_call_arguments.delta', [
            'item_id' => $fcItemId,
            'output_index' => $this->outputIndex,
            'delta' => $argumentsJson,
            'sequence_number' => $this->nextSeq(),
        ]);

        yield $this->formatEvent('response.function_call_arguments.done', [
            'item_id' => $fcItemId,
            'name' => $content['tool_name'],
            'output_index' => $this->outputIndex,
            'arguments' => $argumentsJson,
            'sequence_number' => $this->nextSeq(),
        ]);

        $fcItem = [
            'id' => $fcItemId,
            'type' => 'function_call',
            'call_id' => $content['tool_id'],
            'name' => $content['tool_name'],
            'arguments' => $argumentsJson,
            'status' => 'completed',
        ];
        $this->outputItems[] = $fcItem;

        yield $this->formatEvent('response.output_item.done', [
            'output_index' => $this->outputIndex,
            'item' => $fcItem,
            'sequence_number' => $this->nextSeq(),
        ]);

        ++$this->outputIndex;
    }

    private function handleToolResult(array $content): iterable
    {
        $fcItemId = $this->generateId('fc');

        $fcItem = [
            'id' => $fcItemId,
            'type' => 'function_call',
            'call_id' => $content['tool_id'],
            'name' => $content['tool_name'],
            'result' => $content['result'],
            'status' => 'completed',
        ];
        $this->outputItems[] = $fcItem;

        yield $this->formatEvent('response.output_item.added', [
            'output_index' => $this->outputIndex,
            'item' => $fcItem,
            'sequence_number' => $this->nextSeq(),
        ]);

        yield $this->formatEvent('response.output_item.done', [
            'output_index' => $this->outputIndex,
            'item' => $fcItem,
            'sequence_number' => $this->nextSeq(),
        ]);

        ++$this->outputIndex;
    }

    private function handleUsage(array $content): iterable
    {
        $this->usage['input_tokens'] += $content['prompt_tokens'] ?? 0;
        $this->usage['output_tokens'] += $content['completion_tokens'] ?? 0;
        $this->usage['total_tokens'] = $this->usage['input_tokens'] + $this->usage['output_tokens'];

        yield from [];
    }

    private function closeMessageItem(): iterable
    {
        yield $this->formatEvent('response.output_text.done', [
            'item_id' => $this->msgItemId,
            'output_index' => $this->outputIndex,
            'content_index' => 0,
            'text' => $this->fullContent,
            'sequence_number' => $this->nextSeq(),
        ]);

        yield $this->formatEvent('response.content_part.done', [
            'item_id' => $this->msgItemId,
            'output_index' => $this->outputIndex,
            'content_index' => 0,
            'part' => ['type' => 'output_text', 'text' => $this->fullContent, 'annotations' => []],
            'sequence_number' => $this->nextSeq(),
        ]);

        $msgItem = [
            'id' => $this->msgItemId,
            'type' => 'message',
            'role' => 'assistant',
            'content' => [['type' => 'output_text', 'text' => $this->fullContent, 'annotations' => []]],
            'status' => 'completed',
        ];
        $this->outputItems[] = $msgItem;

        yield $this->formatEvent('response.output_item.done', [
            'output_index' => $this->outputIndex,
            'item' => $msgItem,
            'sequence_number' => $this->nextSeq(),
        ]);

        ++$this->outputIndex;
        $this->inMessage = false;
        $this->fullContent = '';
        $this->msgItemId = null;
    }

    private function handleThinkingDelta(string $delta): iterable
    {
        if (!$this->inThinking) {
            $this->thinkingItemId = $this->generateId('think');
            $this->inThinking = true;

            yield $this->formatEvent('response.output_item.added', [
                'output_index' => $this->outputIndex,
                'item' => [
                    'id' => $this->thinkingItemId,
                    'type' => 'reasoning',
                ],
                'sequence_number' => $this->nextSeq(),
            ]);

            yield $this->formatEvent('response.reasoning_summary_part.added', [
                'item_id' => $this->thinkingItemId,
                'output_index' => $this->outputIndex,
                'summary_index' => 0,
                'sequence_number' => $this->nextSeq(),
            ]);
        }

        yield $this->formatEvent('response.reasoning_summary_text.delta', [
            'item_id' => $this->thinkingItemId,
            'output_index' => $this->outputIndex,
            'summary_index' => 0,
            'delta' => $delta,
            'sequence_number' => $this->nextSeq(),
        ]);

        $this->fullThinkingText .= $delta;
    }

    private function closeThinking(): iterable
    {
        yield $this->formatEvent('response.reasoning_summary_text.done', [
            'item_id' => $this->thinkingItemId,
            'output_index' => $this->outputIndex,
            'summary_index' => 0,
            'text' => $this->fullThinkingText,
            'sequence_number' => $this->nextSeq(),
        ]);

        yield $this->formatEvent('response.reasoning_summary_part.done', [
            'item_id' => $this->thinkingItemId,
            'output_index' => $this->outputIndex,
            'summary_index' => 0,
            'sequence_number' => $this->nextSeq(),
        ]);

        yield $this->formatEvent('response.output_item.done', [
            'output_index' => $this->outputIndex,
            'item' => [
                'id' => $this->thinkingItemId,
                'type' => 'reasoning',
            ],
            'sequence_number' => $this->nextSeq(),
        ]);

        ++$this->outputIndex;
        $this->inThinking = false;
        $this->thinkingItemId = null;
        $this->fullThinkingText = '';
    }
}
