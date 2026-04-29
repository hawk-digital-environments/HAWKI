<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Assistants\Assistant;
use Illuminate\Support\Collection;

readonly class AssistantRepository
{
    public function all(): Collection
    {
        return Assistant::all();
    }

    public function create(array $data): Assistant
    {
        return Assistant::create($data);
    }

    public function update(Assistant $assistant, array $data): bool
    {
        return $assistant->update($data);
    }

    public function delete(Assistant $assistant): void
    {
        $assistant->delete();
    }

    public function syncTools(Assistant $assistant, array $toolIds): void
    {
        $assistant->aiTools()->sync($toolIds);
    }

    public function replaceUserPrompts(Assistant $assistant, array $prompts): void
    {
        $assistant->userPrompts()->delete();
        $assistant->userPrompts()->createMany($prompts);
    }

    public function loadRelations(Assistant $assistant, array $relations): Assistant
    {
        return $assistant->load($relations);
    }
}
