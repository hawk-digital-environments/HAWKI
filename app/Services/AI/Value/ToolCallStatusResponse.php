<?php
declare(strict_types=1);


namespace App\Services\AI\Value;


use App\Services\AI\Tools\ToolRegistry;
use App\Services\AI\Tools\Value\ToolCallCollection;

/**
 * @todo I am not sure what this response does, I implemented it based on the original code but why it needs to be there 🤷‍♂️
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
