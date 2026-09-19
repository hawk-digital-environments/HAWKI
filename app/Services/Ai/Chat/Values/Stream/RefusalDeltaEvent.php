<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Stream;

/**
 * Incremental refusal fragment.
 */
readonly class RefusalDeltaEvent implements AiStreamEvent
{
    public const string TYPE = 'refusal_delta';

    public function __construct(
        public string $refusal,
        public ?int $blockIndex = null,
    ) {
    }
}
