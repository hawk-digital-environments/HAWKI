<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Stream;

/**
 * Incremental tool-call arguments (a JSON string fragment).
 */
readonly class ToolCallDeltaEvent implements AiStreamEvent
{
    public const string TYPE = 'tool_call_delta';

    public function __construct(
        public string $toolCallId,
        public string $argumentsDelta,
        public ?int $toolCallIndex = null,
        public ?int $blockIndex = null,
    ) {
    }
}
