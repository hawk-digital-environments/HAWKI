<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Messages;

/**
 * Optional per-message metadata carried through the IR.
 */
readonly class MessageMetadata
{
    /**
     * @param null|array<string, mixed> $custom free-form converter data for round-trips
     */
    public function __construct(
        public ?string $messageId = null,
        public ?int $timestamp = null,
        public ?array $custom = null,
    ) {
    }
}
