<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Stream;

/**
 * Opens a tool call: identity and name are known, arguments follow via
 * {@see ToolCallDeltaEvent} fragments.
 */
readonly class ToolCallStartEvent implements AiStreamEvent
{
    public const string TYPE = 'tool_call_start';

    /**
     * @param null|array<string, mixed> $providerMetadata
     */
    public function __construct(
        public string $toolCallId,
        public string $toolName,
        public ?int $toolCallIndex = null,
        public ?int $blockIndex = null,
        public ?array $providerMetadata = null,
    ) {
    }
}
