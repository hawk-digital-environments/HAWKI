<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Messages;

use App\Services\Ai\Chat\Values\Parts\AudioPart;
use App\Services\Ai\Chat\Values\Parts\FilePart;
use App\Services\Ai\Chat\Values\Parts\ImagePart;
use App\Services\Ai\Chat\Values\Parts\TextPart;

/**
 * User input, optionally multimodal (text, images, files, audio).
 */
readonly class UserMessage implements Message
{
    public const string ROLE = 'user';

    /**
     * @param array<int, AudioPart|FilePart|ImagePart|TextPart> $parts
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
     * Concatenates all text parts into a single string (non-text parts are skipped).
     */
    public function text(): string
    {
        $texts = array_filter($this->parts, static fn (mixed $part): bool => $part instanceof TextPart);

        return implode("\n\n", array_map(static fn (TextPart $part): string => $part->text, $texts));
    }
}
