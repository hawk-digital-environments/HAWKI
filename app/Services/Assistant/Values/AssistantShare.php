<?php

declare(strict_types=1);

namespace App\Services\Assistant\Values;

/**
 * One entry of an assistant's share list: the user it is shared with, and
 * whether they may edit on top of using it. Wire shape of the share
 * actions — serialised as-is, never hydrated back.
 */
final readonly class AssistantShare implements \JsonSerializable
{
    public function __construct(
        public int $userId,
        public string $displayName,
        public string $username,
        public bool $canEdit,
        public string|null $avatar,
    ) {
    }

    /**
     * @return array{user_id: int, display_name: string, username: string, can_edit: bool, avatar: string|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'user_id' => $this->userId,
            'display_name' => $this->displayName,
            'username' => $this->username,
            'can_edit' => $this->canEdit,
            'avatar' => $this->avatar,
        ];
    }
}
