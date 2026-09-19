<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values;

/**
 * Why a generation ended, plus the stop sequence that triggered it (when applicable).
 */
readonly class FinishReason
{
    public function __construct(
        public FinishReasonType $reason,
        public ?string $stopSequence = null,
    ) {
    }

    public static function stop(): self
    {
        return new self(reason: FinishReasonType::STOP);
    }
}
