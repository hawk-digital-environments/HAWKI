<?php
declare(strict_types=1);


namespace App\Services\AI\Value;


use App\Services\AI\Tools\ToolRegistry;
use App\Services\AI\Tools\Value\ToolCallCollection;

/**
 * Status response sent while tool calls are being prepared/resolved.
 *
 * The frontend expects a `type: "status"` payload with `status.key = "tool_call"`
 * that lists the capabilities of all tools referenced in the current
 * ToolCallCollection. This allows the UI to react to pending tool calls
 * (e.g. by updating progress or enabling tool-specific interactions)
 * before a final AI response is returned.
 */
readonly class ToolCallStatusResponse extends AiResponse
{
    private function __construct(
        array $capabilities
    )
    {
        parent::__construct(
            content: ['text' => ''],
            isDone: false,
            type: 'status',
            status: [
                'key' => 'tool_call',
                'value' => $capabilities
            ]
        );
    }

    public static function fromToolCallsAndRegistry(ToolCallCollection $toolCalls, ToolRegistry $registry): self
    {
        $capabilities = [];
        foreach ($toolCalls as $toolCall) {
            $tool = $registry->get($toolCall->name);
            if ($tool) {
                $capabilities[] = $tool->getCapability();
            }
        }
        return new self($capabilities);
    }
}
