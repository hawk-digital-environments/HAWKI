<?php

declare(strict_types=1);

namespace App\Services\Assistant\Values;

/**
 * One desired share grant on the write side: the target user and whether
 * they may edit. Built from validated `data.shares` rows via
 * {@see fromArray()}; the read side is {@see AssistantShare}.
 */
final readonly class AssistantShareGrant
{
    public function __construct(
        public int $userId,
        public bool $canEdit = false,
    ) {
    }

    /**
     * @param array{user_id: int, can_edit?: bool} $share
     */
    public static function fromArray(array $share): self
    {
        return new self(
            userId: (int) $share['user_id'],
            canEdit: (bool) ($share['can_edit'] ?? false),
        );
    }
}
