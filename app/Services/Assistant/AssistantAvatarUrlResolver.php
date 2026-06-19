<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Models\Assistants\AssistantAvatar;
use App\Services\Storage\AvatarStorageService;

readonly class AssistantAvatarUrlResolver
{
    public function __construct(private AvatarStorageService $avatarStorage) {}

    public function forUuid(?string $uuid): ?string
    {
        return $uuid
            ? $this->avatarStorage->getUrl($uuid, AssistantAvatar::STORAGE_CATEGORY)
            : null;
    }
}
