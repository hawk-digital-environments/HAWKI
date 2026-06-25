<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantSettingValue;
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

    public function filterByName(Builder $query, string $name): Builder
    {
        // Database independent case insensitive filter
        return $query->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($name).'%']);
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
        $clone = Assistant::create([
            'name' => $source->name,
            'description' => $source->description,
            'system_prompt' => $source->system_prompt,
            'greeting' => $source->greeting,
            'allow_remix' => $source->allow_remix,
            'allow_model_select' => $source->allow_model_select,
            'max_tokens' => $source->max_tokens,
            'temp' => $source->temp,
            'top_p' => $source->top_p,
            'model' => $source->model,
            'detail_description' => $source->detail_description,
            'category_id' => $source->category_id,
            'creator_id' => $creatorId,
            'remixed_creator_id' => $source->creator_id,
            'remixed_assistant_id' => $source->id,
            'release_stage' => 'private',
            'organization_id' => $organizationId,
        ]);

        foreach ($source->settingValues()->get() as $value) {
            AssistantSettingValue::create([
                'assistant_id' => $clone->id,
                'setting_id' => $value->setting_id,
                'value' => $value->value,
            ]);
        }

        return $clone;
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

    public function filterByIsFavorite(Builder $query, User $user, bool $isFavorite): Builder
    {
        $method = $isFavorite ? 'whereHas' : 'whereDoesntHave';

        return $query->$method('favoritedByUsers', fn ($q) => $q->where('user_id', $user->id));
    }

    public function setFavorite(Assistant $assistant, User $user, bool $isFavorite): void
    {
        if ($isFavorite) {
            $user->favoriteAssistants()->syncWithoutDetaching([$assistant->id]);
        } else {
            $user->favoriteAssistants()->detach($assistant->id);
        }
    }

    /**
     * @return list<string>
     */
    public function getUserPromptTexts(Assistant $assistant): array
    {
        return $assistant->user_prompts()->pluck('text')->all();
    }

    public function removeUserPrompts(Assistant $assistant, array $texts): void
    {
        $assistant->user_prompts()->whereIn('text', $texts)->delete();
    }

    public function createUserPrompts(Assistant $assistant, array $texts): void
    {
        $assistant->user_prompts()->createMany(
            array_map(fn (string $text) => ['text' => $text], $texts),
        );
    }
}
