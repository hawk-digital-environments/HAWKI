<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Services\Assistant\Repositories\CategoryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

#[Singleton]
readonly class CategoryService
{
    public function __construct(
        private CategoryRepository $repository,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->all($perPage);
    }
}
