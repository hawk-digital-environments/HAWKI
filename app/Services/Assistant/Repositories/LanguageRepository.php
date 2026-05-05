<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Assistants\Language;
use Illuminate\Support\Collection;

readonly class LanguageRepository
{
    public function all(): Collection
    {
        return Language::orderBy('text')->get();
    }

    public function findOrCreateByText(string $text): Language
    {
        return Language::firstOrCreate(['text' => $text]);
    }
}
