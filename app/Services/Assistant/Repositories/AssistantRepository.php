<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Assistants\Assistant;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Builder;

#[UseModel(Assistant::class)]
class AssistantRepository extends AbstractRepository
{
    public function filterByCategoryText(Builder $query, string $text): Builder
    {
        return $query->whereHas('assistantCategory', static function ($q) use ($text): void {
            $q->where('text', $text);
        });
    }

    public function filterByName(Builder $query, string $name): Builder
    {
        // Database independent case insensitive filter
        return $query->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($name) . '%']);
    }

    public function filterVisibleForUser(Builder $query, User $user): Builder
    {
        return $query->where(static function ($q) use ($user): void {
            $q->whereIn('release_stage', AssistantReleaseStage::publiclyVisibleValues())
                ->orWhere('creator_id', $user->id)
                ->orWhereHas('sharedUsers', static fn ($sq) => $sq->where('user_id', $user->id));
        });
    }

    public function isVisibleTo(Assistant $assistant, User $user): bool
    {
        if (\in_array($assistant->release_stage, AssistantReleaseStage::publiclyVisibleCases(), true)) {
            return true;
        }

        if ($assistant->creator_id === $user->id) {
            return true;
        }

        return $assistant->sharedUsers()
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Is the user an administrator of the given organization?
     */
    public function isAdminOf(User $user, ?Organization $organization): bool
    {
        if (null === $organization) {
            return false;
        }

        return $user->organizations()
            ->wherePivot('role', 'admin')
            ->where('organizations.id', $organization->id)
            ->exists();
    }

    /**
     * "Privileged" tier (M): the assistant's creator or an admin of the
     * assistant's organization. Used for sensitive relationships
     * (assistant_tags, assistant_feedback, assistant_review, assistant_setting_values, ai_tools).
     */
    public function isPrivileged(User $user, Assistant $assistant): bool
    {
        if ($assistant->creator_id === $user->id) {
            return true;
        }

        return $this->isAdminOf($user, $assistant->organization);
    }

    /**
     * Query scope equivalent of the privileged tier, for scoping collections of
     * assistants (or child resources via whereHas('assistant', ...)) to those the
     * user may manage: assistants they created or that belong to an organization
     * they administer.
     */
    public function filterPrivilegedForUser(Builder $query, User $user): Builder
    {
        return $query->where(static function (Builder $q) use ($user): void {
            $q->where('creator_id', $user->id)
                ->orWhereHas('organization', static function (Builder $orgQuery) use ($user): void {
                    $orgQuery->whereHas('users', static function (Builder $userQuery) use ($user): void {
                        $userQuery->where('users.id', $user->id)
                            ->where('organization_user.role', 'admin');
                    });
                });
        });
    }

    public function clone(Assistant $source, int $creatorId, ?int $organizationId = null): Assistant
    {
        // Sensitive fields (creator_id, organization_id, remixed_*,
        // release_stage) are intentionally not mass-assignable on the model;
        // forceFill bypasses $fillable for this server-driven clone path.
        $clone = (new Assistant())->forceFill([
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
            'release_stage' => AssistantReleaseStage::PRIVATE->value,
            'organization_id' => $organizationId,
        ]);
        $clone->save();

        foreach ($source->settingValues()->get() as $value) {
            $clone->settingValues()->create([
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

    public function setReleaseStage(Assistant $assistant, AssistantReleaseStage $stage): bool
    {
        if ($assistant->release_stage === $stage) {
            return false;
        }

        $assistant->release_stage = $stage;
        $assistant->save();

        return true;
    }

    public function setRequestedReleaseStage(Assistant $assistant, AssistantReleaseStage $stage): void
    {
        $assistant->requested_release_stage = $stage;
        $assistant->save();
    }

    public function clearRequestedReleaseStage(Assistant $assistant): void
    {
        if (null === $assistant->requested_release_stage) {
            return;
        }

        $assistant->requested_release_stage = null;
        $assistant->save();
    }

    public function loadRelations(Assistant $assistant, array $relations): Assistant
    {
        return $assistant->load($relations);
    }

    public function findOneByHandle(string $handle): ?Assistant
    {
        return $this->getQuery()->where('handle', $handle)->first();
    }

    public function filterByIsFavorite(Builder $query, User $user, bool $isFavorite): Builder
    {
        $method = $isFavorite ? 'whereHas' : 'whereDoesntHave';

        return $query->{$method}('favoritedByUsers', static fn ($q) => $q->where('user_id', $user->id));
    }

    public function setFavorite(Assistant $assistant, User $user, bool $isFavorite): void
    {
        if ($isFavorite) {
            $user->favoriteAssistants()->syncWithoutDetaching([$assistant->id]);
        } else {
            $user->favoriteAssistants()->detach($assistant->id);
        }
    }
}
