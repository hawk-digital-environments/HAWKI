<?php

declare(strict_types=1);

namespace App\Services\Assistant\Values;

/**
 * One entry of an assistant's share list: the user it is shared with, and
 * whether they may edit on top of using it. Read model for the share
 * actions' relationship document (see AssistantController::shareDocument).
 */
final readonly class AssistantShare
{
    public function __construct(
        public int $userId,
        public string $displayName,
        public string $username,
        public bool $canEdit,
        public string|null $avatar,
    ) {
    }
}
