<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Parts;

/**
 * The outcome of a tool invocation, linked back to its {@see ToolCallPart} by call id
 * (tool side of the tool loop).
 */
readonly class ToolResultPart implements ContentPart
{
    public const string TYPE = 'tool_result';

    /**
     * @param array<int, ContentPart>|string $result           plain-text result or multimodal content parts
     * @param null|array<string, mixed>      $providerMetadata opaque provider-specific fields, tagged by the formatter that captured them
     */
    public function __construct(
        public string $toolCallId,
        public mixed $result,
        public ?bool $isError = null,
        public ?array $providerMetadata = null,
    ) {
    }
}
