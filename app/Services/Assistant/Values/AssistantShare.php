<?php

declare(strict_types=1);

namespace App\Services\Assistant\Values;

/**
 * One entry of an assistant's audience or co-author list. Read model for
 * the share actions' relationship document (see AssistantController::shareDocument).
 */
final readonly class AssistantShare
{
    public function __construct(
        public int $userId,
        public string $displayName,
        public string $username,
        public string|null $avatar,
    ) {
    }
}
