<?php

declare(strict_types=1);

namespace App\Services\Assistant\Values;

/**
 * One desired share grant on the write side: the target user and whether
 * they may edit. Built from validated `data` rows — JSON:API resource
 * identifiers with the share `meta` — via {@see fromArray()}; the read
 * side is {@see AssistantShare}.
 */
final readonly class AssistantShareGrant
{
    public function __construct(
        public int $userId,
        public bool $canEdit = false,
    ) {
    }

    /**
     * @param array{type?: string, id: int|string, meta?: array{can_edit?: bool}} $identifier
     */
    public static function fromArray(array $identifier): self
    {
        return new self(
            userId: (int) $identifier['id'],
            canEdit: (bool) ($identifier['meta']['can_edit'] ?? false),
        );
    }
}
