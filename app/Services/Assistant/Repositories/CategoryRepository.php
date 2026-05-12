<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Assistants\Category;
use App\Utils\JsonApiPagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

readonly class CategoryRepository
{
    public function all(int $perPage = 15): LengthAwarePaginator
    {
        return Category::orderBy('text')->paginate($perPage, ['*'], JsonApiPagination::pageName(), JsonApiPagination::pageNumber());
    }

    public function findOrCreateByText(string $text): Category
    {
        return Category::firstOrCreate(['text' => $text]);
    }
}
