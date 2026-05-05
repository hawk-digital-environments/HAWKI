<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Services\Assistant\Repositories\LanguageRepository;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;

#[Singleton]
readonly class LanguageService
{
    public function __construct(
        private LanguageRepository $repository,
    ) {}

    public function list(): Collection
    {
        return $this->repository->all();
    }
}
