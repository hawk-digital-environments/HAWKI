<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Parts;

/**
 * Plain text content — the base content part every wire format supports.
 */
readonly class TextPart implements ContentPart
{
    public const string TYPE = 'text';

    /**
     * @param null|array<string, mixed> $providerMetadata opaque provider-specific fields, tagged by the formatter that captured them
     */
    public function __construct(
        public string $text,
        public ?array $providerMetadata = null,
    ) {
    }

    public static function from(string $text): self
    {
        return new self(text: $text);
    }
}
