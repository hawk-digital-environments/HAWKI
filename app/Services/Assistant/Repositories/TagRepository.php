<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Assistants\Tag;

readonly class TagRepository
{
    public function findIdsByTexts(array $texts): array
    {
        return Tag::whereIn('text', $texts)->pluck('id', 'text')->toArray();
    }

    public function insertMany(array $tags): void
    {
        Tag::insert($tags);
    }
}
