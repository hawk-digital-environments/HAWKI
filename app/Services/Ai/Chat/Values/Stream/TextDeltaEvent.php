<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Stream;

/**
 * Incremental assistant text fragment.
 */
readonly class TextDeltaEvent implements AiStreamEvent
{
    public const string TYPE = 'text_delta';

    public function __construct(
        public string $text,
        public ?int $blockIndex = null,
    ) {
    }
}
