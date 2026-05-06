<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Assistants\Language;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

readonly class LanguageRepository
{
    public function all(int $perPage = 15): LengthAwarePaginator
    {
        return Language::orderBy('text')->paginate($perPage);
    }

    public function findOrCreateByText(string $text): Language
    {
        return Language::firstOrCreate(['text' => $text]);
    }
}
