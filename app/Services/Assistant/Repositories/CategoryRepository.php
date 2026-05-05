<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Assistants\Category;
use Illuminate\Support\Collection;

readonly class CategoryRepository
{
    public function all(): Collection
    {
        return Category::orderBy('text')->get();
    }

    public function findOrCreateByText(string $text): Category
    {
        return Category::firstOrCreate(['text' => $text]);
    }
}
