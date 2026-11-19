<?php

declare(strict_types=1);

namespace App\Services\Assistant\Repositories;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantSharedUser;
use App\Models\Assistants\AssistantShareLink;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Exceptions\UnshareableUserException;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantShare;
use App\Services\Assistant\Values\AssistantShareGrant;
use App\Services\Storage\Values\StoredFileIdentifier;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;
use App\Services\System\Time\CarbonClockInterface;
use App\Services\Users\Repositories\UserRepository;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

#[UseModel(Assistant::class)]
class AssistantRepository extends AbstractRepository
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly CarbonClockInterface $clock,
    ) {
    }

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

    /**
     * Org admins see another user's private or draft assistant only while a
     * review record exists — deciding or re-deciding needs it; anything
     * unsubmitted stays the creator's business.
     */
    public function isVisibleTo(Assistant $assistant, User $user): bool
    {
        if (\in_array($assistant->release_stage, AssistantReleaseStage::publiclyVisibleCases(), true)) {
            return true;
        }

        if ($assistant->creator_id === $user->id) {
            return true;
        }

        if ($this->isAdminOf($user, $assistant->organization_id) && null !== $assistant->assistantReview) {
            return true;
        }

        return $assistant->sharedUsers()
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Assistants another user shared with the given one (the `sharedUsers`
     * pivot), independent of their release stage.
     */
    public function filterSharedWithUser(Builder $query, User $user): Builder
    {
        return $query->whereHas('sharedUsers', static fn ($q) => $q->where('user_id', $user->id));
    }

    /**
     * Is the user an administrator of the given organization?
     */
    public function isAdminOf(User $user, null|int|Organization $organization): bool
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        if (null === $organizationId) {
            return false;
        }

        return $user->organizations()
            ->wherePivot('role', 'admin')
            ->where('organizations.id', $organizationId)
            ->exists();
    }

    /**
     * "Privileged" tier (M): the assistant's creator, an admin of the
     * assistant's organization, or a user the assistant is shared with under
     * the edit grant. Used for sensitive relationships (assistant_tags,
     * assistant_feedback, assistant_review, assistant_setting_values,
     * ai_tools).
     */
    public function isPrivileged(User $user, Assistant $assistant): bool
    {
        if ($assistant->creator_id === $user->id) {
            return true;
        }

        if ($this->isAdminOf($user, $assistant->organization_id)) {
            return true;
        }

        return $this->canEditViaShare($user, $assistant);
    }

    /**
     * Query scope equivalent of the privileged tier, for scoping collections of
     * assistants (or child resources via whereHas('assistant', ...)) to those the
     * user may manage: assistants they created, that belong to an organization
     * they administer, or that are shared with them under the edit grant.
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
                })
                ->orWhereHas('sharedUsers', static function (Builder $shareQuery) use ($user): void {
                    $shareQuery->where('user_id', $user->id)
                        ->where('assistant_shared_users.can_edit', true);
                });
        });
    }

    /**
     * Whether the assistant is shared with the user under the edit grant.
     * Uses the schema-eager-loaded pivots when present, else one exists()
     * query (see AssistantSchema::with()).
     */
    public function canEditViaShare(User $user, Assistant $assistant): bool
    {
        if ($assistant->relationLoaded('sharedUserPivots')) {
            return $assistant->sharedUserPivots->contains(
                static fn (AssistantSharedUser $pivot): bool => $pivot->user_id === $user->id && $pivot->can_edit,
            );
        }

        return $assistant->sharedUsers()
            ->where('user_id', $user->id)
            ->where('assistant_shared_users.can_edit', true)
            ->exists();
    }

    /**
     * The assistant's current share list, newest share first.
     *
     * Deliberately without the User model's global scopes: the `access`
     * scope would hide freshly shared strangers from the very list that
     * shared them.
     *
     * @return list<AssistantShare>
     */
    public function listShares(Assistant $assistant): array
    {
        return $assistant->sharedUsers()
            ->withoutGlobalScopes()
            ->orderByPivot('created_at', 'desc')
            ->orderByPivot('user_id')
            ->get()
            ->map(static fn (User $user) => new AssistantShare(
                userId: $user->id,
                displayName: $user->name ?: $user->username,
                username: $user->username,
                canEdit: (bool) ($user->getRelation('pivot')->can_edit ?? false),
                avatar: StoredFileIdentifier::tryFromUserAvatar($user)?->__toString(),
            ))
            ->all();
    }

    /**
     * Replaces the assistant's share list with the given set — attach new,
     * update changed `can_edit` flags, detach absent. Returns the resulting
     * list so callers need no follow-up fetch.
     *
     * @param list<AssistantShareGrant> $shares
     * @return list<AssistantShare>
     * @throws UnshareableUserException when a target cannot be shared with
     */
    public function syncShares(Assistant $assistant, array $shares): array
    {
        $pivotData = [];
        foreach ($shares as $share) {
            $pivotData[$share->userId] = ['can_edit' => $share->canEdit];
        }

        $this->assertShareableTargets($assistant, array_keys($pivotData));

        $assistant->sharedUsers()->sync($pivotData);

        return $this->listShares($assistant);
    }

    /**
     * Adds one user to the share list, as a share link redemption does. An
     * existing grant is only ever widened, never narrowed; the creator is a no-op.
     *
     * @throws UnshareableUserException when the user cannot be shared with
     */
    public function grantShare(Assistant $assistant, int $userId, bool $canEdit): void
    {
        if ($assistant->creator_id === $userId) {
            return;
        }

        $this->assertShareableTargets($assistant, [$userId]);

        $pivot = AssistantSharedUser::query()->firstOrNew([
            'assistant_id' => $assistant->id,
            'user_id' => $userId,
        ]);
        $pivot->can_edit = $pivot->can_edit || $canEdit;
        $pivot->save();
    }

    /**
     * Stores a new share link and returns its plain token; only the hash is persisted.
     */
    public function createShareLink(Assistant $assistant, User $creator, bool $canEdit, CarbonInterface $expiresAt): string
    {
        $token = Str::random(48);

        AssistantShareLink::query()->create([
            'assistant_id' => $assistant->id,
            'created_by' => $creator->id,
            'token_hash' => self::hashShareToken($token),
            'can_edit' => $canEdit,
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    public function findRedeemableShareLink(string $token): ?AssistantShareLink
    {
        return AssistantShareLink::query()
            ->with('assistant')
            ->where('token_hash', self::hashShareToken($token))
            ->where('expires_at', '>', $this->clock->now())
            ->first();
    }

    private static function hashShareToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Every target must be a real human user (not removed, not a system/app
     * account) and not the creator, who already owns the assistant.
     *
     * @param list<int> $userIds
     */
    private function assertShareableTargets(Assistant $assistant, array $userIds): void
    {
        if ([] === $userIds) {
            return;
        }

        $validIds = $this->users->findShareableIds($userIds);

        $invalid = array_values(array_diff($userIds, $validIds));
        if (\in_array($assistant->creator_id, $userIds, true)) {
            $invalid[] = $assistant->creator_id;
        }

        if ([] !== $invalid) {
            throw UnshareableUserException::forIds($invalid);
        }
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

    /**
     * Re-fetch the assistant by key under a row lock, for decisions made
     * inside the caller's transaction. Null when the row vanished between
     * the caller's fetch and this lock (a lost race).
     */
    public function lockForUpdate(Assistant $assistant): ?Assistant
    {
        return $this->getQuery()->whereKey($assistant->getKey())->lockForUpdate()->first();
    }

    public function findOneByHandle(string $handle): ?Assistant
    {
        return $this->getQuery()->where('handle', $handle)->first();
    }

    /**
     * Which of the given handles already exist, in the order they were
     * asked for. Deliberately not visibility-scoped: the callers ask about
     * the handle namespace's global uniqueness, and the answer discloses
     * existence only — the same disclosure a rejected create with a taken
     * handle already makes.
     *
     * @param list<string> $handles
     * @return list<string>
     */
    public function findExistingHandles(array $handles): array
    {
        $existing = $this->getQuery()
            ->whereIn('handle', $handles)
            ->pluck('handle')
            ->all();

        return array_values(array_intersect($handles, $existing));
    }

    /**
     * Deletes a creator's draft and private assistants, including ones with
     * a pending publication request — the current stage decides, not the
     * requested one. Public assistants (organizational/federated) are kept.
     *
     * Deletes row by row through the model so the deleting observer fires per
     * assistant and cleans up the stored attachment files ({@see \App\Services\Assistant\Observers\AssistantObserver::deleting()});
     * a bulk delete would skip those model events and orphan the files.
     *
     * @return int The number of assistants deleted.
     */
    public function deleteNonPublicForCreator(User $user): int
    {
        $assistants = $this->getQuery()
            ->where('creator_id', $user->id)
            ->whereIn('release_stage', [
                AssistantReleaseStage::DRAFT->value,
                AssistantReleaseStage::PRIVATE->value,
            ])
            ->get();

        $assistants->each(static fn (Assistant $assistant) => $assistant->delete());

        return $assistants->count();
    }

    /**
     * Detaches a user's pivot rows on assistants they did not create
     * (favorites and shares received from others). User removal is
     * soft, so the FK cascades never fire — without this the pivots
     * would linger invisibly forever.
     */
    public function detachPivotsForUser(User $user): void
    {
        $user->favoriteAssistants()->detach();
        $user->sharedAssistants()->detach();
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
