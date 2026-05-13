<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Database\Eloquent\Builder;

readonly class AssistantRepository
{
    public function filterByCategoryText(Builder $query, string $text): Builder
    {
        return $query->whereHas('category', function ($q) use ($text) {
            $q->where('text', $text);
        });
    }

    public function filterVisibleForUser(Builder $query, User $user): Builder
    {
        return $query->where(function ($q) use ($user) {
            $q->where('release_stage', '!=', 'private')
                ->orWhere('creator_id', $user->id);
        });
    }

    public function isVisibleTo(Assistant $assistant, User $user): bool
    {
        return $assistant->release_stage !== 'private' || $assistant->creator_id === $user->id;
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
