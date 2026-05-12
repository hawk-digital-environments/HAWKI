<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Assistant\Values\ReleaseStage;
use App\Utils\JsonApiPagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

readonly class AssistantRepository
{
    public function all(?User $user = null, int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Assistant::class)
            ->allowedFilters(
                AllowedFilter::exact('category', 'category.text'),
            )
            ->when($user !== null, fn ($q) => $q->where(
                fn ($q) => $q
                    ->where('release_stage', '!=', 'private')
                    ->orWhere('creator_id', $user->id)
            ))
            ->paginate($perPage, ['*'], JsonApiPagination::pageName(), JsonApiPagination::pageNumber());
    }

    public function isVisibleTo(Assistant $assistant, User $user): bool
    {
        return $assistant->release_stage !== 'private' || $assistant->creator_id === $user->id;
    }

    public function create(array $data): Assistant
    {
        return Assistant::create($data);
    }

    public function update(Assistant $assistant, array $data): array
    {
        $assistant->fill($data);

        $assistant->save();

        return $assistant->getChanges();
    }

    public function delete(Assistant $assistant): void
    {
        $assistant->delete();
    }

    public function clone(Assistant $source, int $creatorId, ?int $organizationId = null): Assistant
    {
        return Assistant::create([
            'name' => $source->name,
            'description' => $source->description,
            'system_prompt' => $source->system_prompt,
            'greeting' => $source->greeting,
            'allow_remix' => $source->allow_remix,
            'allow_model_select' => $source->allow_model_select,
            'model_length' => $source->model_length,
            'model_temp' => $source->model_temp,
            'model_top_p' => $source->model_top_p,
            'model' => $source->model,
            'formality' => $source->formality,
            'detail_description' => $source->detail_description,
            'language_id' => $source->language_id,
            'category_id' => $source->category_id,
            'creator_id' => $creatorId,
            'remixed_creator_id' => $source->creator_id,
            'remixed_assistant_id' => $source->id,
            'release_stage' => 'private',
            'organization_id' => $organizationId,
        ]);
    }

    public function syncTools(Assistant $assistant, array $toolIds): array
    {
        return $assistant->ai_tools()->sync($toolIds);
    }

    public function syncTags(Assistant $assistant, array $tagIds): array
    {
        return $assistant->tags()->sync($tagIds);
    }

    public function replaceUserPrompts(Assistant $assistant, array $prompts): bool
    {
        $existing = $assistant->user_prompts->pluck('text')->toArray();
        $new = collect($prompts)->pluck('text')->toArray();

        if ($existing === $new) {
            return false;
        }

        $assistant->user_prompts()->delete();
        $assistant->user_prompts()->createMany($prompts);

        return true;
    }

    public function setReleaseStage(Assistant $assistant, ReleaseStage $stage): bool
    {
        if ($assistant->release_stage === $stage->value) {
            return false;
        }

        $assistant->release_stage = $stage->value;
        $assistant->save();

        return true;
    }

    public function loadRelations(Assistant $assistant, array $relations): Assistant
    {
        return $assistant->load($relations);
    }
}
