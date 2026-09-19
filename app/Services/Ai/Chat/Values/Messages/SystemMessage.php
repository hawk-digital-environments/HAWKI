<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Messages;

use App\Services\Ai\Chat\Values\Parts\TextPart;

/**
 * System-level instructions. Only {@see TextPart} content is representable.
 */
readonly class SystemMessage implements Message
{
    public const string ROLE = 'system';

    /**
     * @param array<int, TextPart> $parts
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
     * Concatenates all text parts into a single instruction string.
     */
    public function text(): string
    {
        return implode("\n\n", array_map(static fn (TextPart $part): string => $part->text, $this->parts));
    }
}
