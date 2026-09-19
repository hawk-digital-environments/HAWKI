<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Parts;

/**
 * A tool invocation emitted by the model (assistant side of the tool loop).
 */
readonly class ToolCallPart implements ContentPart
{
    public const string TYPE = 'tool_call';

    /**
     * @param array<string, mixed>      $toolInput        decoded JSON arguments of the call
     * @param null|array<string, mixed> $providerMetadata opaque provider-specific fields, tagged by the formatter that captured them
     */
    public function __construct(
        public string $toolCallId,
        public string $toolName,
        public array $toolInput,
        public ?ToolType $toolType = null,
        public ?array $providerMetadata = null,
    ) {
    }
}
