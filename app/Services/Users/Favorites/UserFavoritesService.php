<?php

declare(strict_types=1);

namespace App\Services\Users\Favorites;

use App\Models\User;
use App\Models\UserFavoriteValue;
use App\Services\System\UserTypes\UserContext;
use App\Services\Users\Exceptions\MissingAuthenticatedUserException;
use App\Services\Users\Repositories\UserFavoriteValueRepository;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Collection;

/**
 * Central access point for per-user favorites — the backend half of the generic
 * "favorite something" mechanism.
 *
 * A favorite is a set-membership record addressed by the triple
 * **namespace** (logical owner, e.g. `'hawki-core'`), **type** (the kind of item,
 * e.g. `'ai-model'`) and **identifier** (the item's id). All three are free-form
 * validated strings — there is deliberately no registry of allowed values, so any
 * feature (or later plugin) can favorite anything without backend plumbing.
 *
 * Every method resolves the authenticated user from the {@see UserContext} **per
 * call** (never `Auth::user()`) and throws {@see MissingAuthenticatedUserException}
 * for guests and registering users — favorites have no session/runtime storage
 * backends; they exist per user account only.
 *
 * Reads are cached in an identity map keyed by **user id** — never by a single
 * slot — so a user switch inside a long-lived worker process can never leak
 * another user's favorite set. Mutations refresh the calling user's cache entry.
 *
 * @api
 */
#[Singleton()]
class UserFavoritesService
{
    /**
     * Namespace used when a caller omits the namespace argument — the same
     * logical owner as core user settings and public configs.
     */
    public const string DEFAULT_NAMESPACE = 'hawki-core';

    /**
     * @var array<int|string, array<string, array<string, array<string, UserFavoriteValue>>>>
     *                                                                                        Cached favorites per user id, shaped `[namespace][type] => identifier => row`
     *                                                                                        for O(1) {@see isFavorite()} lookups
     */
    private array $map = [];

    public function __construct(
        private readonly UserContext $userContext,
        private readonly UserFavoriteValueRepository $repository,
    ) {
    }

    /**
     * Returns whether the authenticated user has favorited the addressed item.
     *
     * @throws MissingAuthenticatedUserException when no user is authenticated
     */
    public function isFavorite(string $type, string $identifier, ?string $namespace = null): bool
    {
        $favorites = $this->resolveUserFavorites();

        return isset($favorites[$this->resolveNamespace($namespace)][$type][$identifier]);
    }

    /**
     * Marks the addressed item as a favorite of the authenticated user. Idempotent:
     * favoriting an already-favorited item returns the existing row unchanged.
     *
     * @throws MissingAuthenticatedUserException when no user is authenticated
     */
    public function markAsFavorite(string $type, string $identifier, ?string $namespace = null): UserFavoriteValue
    {
        $user = $this->resolveUser();

        $favorite = $this->repository->createForUser(
            $user,
            $type,
            $identifier,
            $this->resolveNamespace($namespace),
        );

        $this->map[$user->id][$favorite->namespace][$favorite->type][$favorite->identifier] = $favorite;

        return $favorite;
    }

    /**
     * Removes the addressed item from the authenticated user's favorites. Idempotent:
     * removing an absent favorite is a no-op.
     *
     * @throws MissingAuthenticatedUserException when no user is authenticated
     */
    public function removeAsFavorite(string $type, string $identifier, ?string $namespace = null): void
    {
        $user = $this->resolveUser();

        $this->repository->deleteForUser($user, $type, $identifier, $this->resolveNamespace($namespace));

        unset($this->map[$user->id][$this->resolveNamespace($namespace)][$type][$identifier]);
    }

    /**
     * Returns all favorites of the authenticated user, optionally narrowed by
     * type and/or namespace. Unlike the other methods, a `null` namespace here
     * means "no filter" — it lists every namespace, not the default one.
     *
     * @throws MissingAuthenticatedUserException when no user is authenticated
     *
     * @return Collection<int, UserFavoriteValue>
     */
    public function getFavorites(?string $type = null, ?string $namespace = null): Collection
    {
        return $this->repository->getForUser($this->resolveUser(), $type, $namespace);
    }

    /**
     * Returns the authenticated user or throws — favorites require a user account.
     */
    private function resolveUser(): User
    {
        $user = $this->userContext->getUser();

        if (!$user instanceof User) {
            throw MissingAuthenticatedUserException::forFavoritesService('access favorites');
        }

        return $user;
    }

    /**
     * Returns the calling user's cached favorite set, hydrating it from the
     * repository on first access.
     *
     * @return array<string, array<string, array<string, UserFavoriteValue>>>
     *                                                                        `[namespace][type] => identifier => row`
     */
    private function resolveUserFavorites(): array
    {
        $user = $this->resolveUser();

        if (isset($this->map[$user->id])) {
            return $this->map[$user->id];
        }

        /** @var array<string, array<string, array<string, UserFavoriteValue>>> $favorites */
        $favorites = [];

        foreach ($this->repository->getForUser($user) as $favorite) {
            $favorites[$favorite->namespace][$favorite->type][$favorite->identifier] = $favorite;
        }

        return $this->map[$user->id] = $favorites;
    }

    /**
     * Resolves the namespace argument, defaulting to {@see DEFAULT_NAMESPACE}.
     */
    private function resolveNamespace(?string $namespace): string
    {
        return $namespace ?? self::DEFAULT_NAMESPACE;
    }
}
