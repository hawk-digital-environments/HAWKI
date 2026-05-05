<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Services\Assistant\Repositories\CategoryRepository;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;

#[Singleton]
readonly class CategoryService
{
    public function __construct(
        private CategoryRepository $repository,
    ) {}

    public function list(): Collection
    {
        return $this->repository->all();
    }
}
