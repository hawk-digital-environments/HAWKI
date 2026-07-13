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
        if (\in_array($assistant->release_stage, AssistantReleaseStage::publiclyVisibleValues(), true)) {
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
     * (assistant_tags, assistant_feedback, assistant_review, assistant_setting_values) and ai_tools edits.
     */
    public function isPrivileged(User $user, Assistant $assistant): bool
    {
        if ($assistant->creator_id === $user->id) {
            return true;
        }

        return $this->isAdminOf($user, $assistant->organization);
    }

    /**
     * "Collaborator" tier (C): creator, org admin, or an explicitly shared user.
     * Public viewers (visible only via release stage) are excluded. Used for
     * viewing ai_tools.
     */
    public function canCollaborate(User $user, Assistant $assistant): bool
    {
        if ($this->isPrivileged($user, $assistant)) {
            return true;
        }

        return $assistant->sharedUsers()
            ->where('user_id', $user->id)
            ->exists();
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

    /**
     * Query scope equivalent of the collaborator tier: creator, org admin, or
     * an explicitly shared user. Used to scope the assistant collection when a
     * client requests the ai_tools include path.
     */
    public function filterCollaborateForUser(Builder $query, User $user): Builder
    {
        return $query->where(static function (Builder $q) use ($user): void {
            $q->where('creator_id', $user->id)
                ->orWhereHas('organization', static function (Builder $orgQuery) use ($user): void {
                    $orgQuery->whereHas('users', static function (Builder $userQuery) use ($user): void {
                        $userQuery->where('users.id', $user->id)
                            ->where('organization_user.role', 'admin');
                    });
                })
                ->orWhereHas('sharedUsers', static function (Builder $sharedQuery) use ($user): void {
                    $sharedQuery->where('user_id', $user->id);
                });
        });
    }

    public function clone(Assistant $source, int $creatorId, ?int $organizationId = null): Assistant
    {
        $clone = $this->getQuery()->create([
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
