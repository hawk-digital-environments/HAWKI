<?php
declare(strict_types=1);


namespace App\Services\AI\Providers\OpenAi\Request;


use App\Services\AI\Tools\Value\ToolCall;
use App\Services\AI\Tools\Value\ToolCallCollection;

trait OpenAiToolCallingTrait
{
    /**
     * Parse tool calls from Response API output array
     */
    private function parseToolCalls(array $output): ToolCallCollection
    {
        $toolCalls = [];

        foreach ($output as $item) {
            if (($item['type'] ?? '') === 'function_call' && ($item['status'] ?? '') === 'completed') {
                $arguments = json_decode($item['arguments'] ?? '{}', true);

                $toolCalls[] = new ToolCall(
                    id: $item['call_id'] ?? $item['id'] ?? 'unknown',
                    type: 'function',
                    name: $item['name'] ?? 'unknown',
                    arguments: $arguments,
                    index: null
                );
            }
        }

        return new ToolCallCollection(...$toolCalls);
    }
}
