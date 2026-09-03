<?php

declare(strict_types=1);

namespace App\Services\Users\Repositories;

use App\Models\User;
use App\Models\UserFavoriteValue;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Repository for the per-user favorites rows in `user_favorite_values`.
 *
 * The table stores one row per favorited item, addressed by the
 * (`namespace`, `type`, `identifier`, `user_id`) quadruple — the unique index on it
 * is the idempotency guard for {@see createForUser()}.
 *
 * All methods take the {@see User} explicitly (no auth-guard dependence in queue
 * workers or migrations). In request context the model's `'access'` contextual scope
 * ({@see \App\Models\Scopes\Generic\BelongsToUserScope}) additionally confines every
 * query to the authenticated user as defense-in-depth; in CLI context the explicit
 * user argument is the only — and authoritative — filter.
 *
 * @extends AbstractRepositoryWithContextualScopes<UserFavoriteValue>
 */
class UserFavoriteValueRepository extends AbstractRepositoryWithContextualScopes
{
    /**
     * Returns all favorites of the given user, newest first, optionally narrowed
     * by type and/or namespace.
     *
     * @return Collection<int, UserFavoriteValue>
     */
    public function getForUser(User $user, ?string $type = null, ?string $namespace = null): Collection
    {
        /** @var Collection<int, UserFavoriteValue> */
        return $this->getFavoritesQuery($user, $type, $namespace)->get();
    }

    /**
     * Returns whether the given user has favorited the addressed item.
     */
    public function existsForUser(User $user, string $type, string $identifier, string $namespace): bool
    {
        return $this->getQuery()
            ->where('user_id', $user->id)
            ->where('namespace', $namespace)
            ->where('type', $type)
            ->where('identifier', $identifier)
            ->exists();
    }

    /**
     * Creates a favorite row for the given user, returning the existing row when the
     * quadruple already exists (idempotent create — the unique index decides).
     */
    public function createForUser(User $user, string $type, string $identifier, string $namespace): UserFavoriteValue
    {
        try {
            return $this->getQuery()->create([
                'user_id' => $user->id,
                'namespace' => $namespace,
                'type' => $type,
                'identifier' => $identifier,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->findOneForUserOrFail($user, $type, $identifier, $namespace);
        }
    }

    /**
     * Deletes the addressed favorite of the given user. Returns whether a row was
     * actually removed — removing an absent favorite is a no-op, not an error.
     */
    public function deleteForUser(User $user, string $type, string $identifier, string $namespace): bool
    {
        return (bool) $this->getFavoritesQuery($user, $type, $namespace)
            ->where('identifier', $identifier)
            ->delete();
    }

    /**
     * Deletes every favorite row of the given user, across all namespaces and types.
     * Used by the user-removal cleanup listener.
     */
    public function deleteAllForUser(User $user): void
    {
        $this->getQuery()
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * Base query for per-user favorites, optionally narrowed by type and namespace.
     */
    private function getFavoritesQuery(User $user, ?string $type, ?string $namespace): Builder
    {
        return $this->getQuery()
            ->where('user_id', $user->id)
            ->when(null !== $type, static fn (Builder $query): Builder => $query->where('type', $type))
            ->when(null !== $namespace, static fn (Builder $query): Builder => $query->where('namespace', $namespace))
            ->latest();
    }

    /**
     * Returns the single row for the quadruple or throws when it is missing —
     * only used right after a constraint violation, where the row must exist.
     */
    private function findOneForUserOrFail(User $user, string $type, string $identifier, string $namespace): UserFavoriteValue
    {
        $favorite = $this->getQuery()
            ->where('user_id', $user->id)
            ->where('namespace', $namespace)
            ->where('type', $type)
            ->where('identifier', $identifier)
            ->first();

        \assert($favorite instanceof UserFavoriteValue);

        return $favorite;
    }
}
