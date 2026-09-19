<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Messages;

use App\Services\Ai\Chat\Values\Parts\CitationPart;
use App\Services\Ai\Chat\Values\Parts\ReasoningPart;
use App\Services\Ai\Chat\Values\Parts\RefusalPart;
use App\Services\Ai\Chat\Values\Parts\TextPart;
use App\Services\Ai\Chat\Values\Parts\ToolCallPart;

/**
 * Model output: text, tool calls, reasoning traces, refusals, and citations.
 */
readonly class AssistantMessage implements Message
{
    public const string ROLE = 'assistant';

    /**
     * @param array<int, CitationPart|ReasoningPart|RefusalPart|TextPart|ToolCallPart> $parts
     */
    public function __construct(
        public array $parts,
        public ?MessageMetadata $metadata = null,
    ) {
    }

    public static function fromText(string $text): self
    {
        return new self(parts: [TextPart::from($text)]);
    }

    /**
     * Concatenates all text parts into a single string (all other parts are skipped).
     */
    public function text(): string
    {
        $texts = array_filter($this->parts, static fn (mixed $part): bool => $part instanceof TextPart);

        return implode('', array_map(static fn (TextPart $part): string => $part->text, $texts));
    }

    /**
     * Returns all tool call parts in emission order.
     *
     * @return array<int, ToolCallPart>
     */
    public function toolCalls(): array
    {
        return array_values(array_filter($this->parts, static fn (mixed $part): bool => $part instanceof ToolCallPart));
    }
}
