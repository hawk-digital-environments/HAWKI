<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Services\Assistant\Repositories\LanguageRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

#[Singleton]
readonly class LanguageService
{
    public function __construct(
        private LanguageRepository $repository,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->all($perPage);
    }
}
