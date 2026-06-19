<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Assistants\AssistantAvatar;

readonly class AssistantAvatarRepository
{
    public function findByName(string $name): ?AssistantAvatar
    {
        return AssistantAvatar::where('name', $name)->first();
    }

    public function store(string $name, string $uuid): AssistantAvatar
    {
        return AssistantAvatar::updateOrCreate(
            ['name' => $name],
            ['uuid' => $uuid],
        );
    }
}
