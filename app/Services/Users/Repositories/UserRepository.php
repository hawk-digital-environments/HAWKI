<?php
declare(strict_types=1);


namespace App\Services\Users\Repositories;


use App\Models\ExtApp;
use App\Models\User;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class UserRepository extends AbstractRepositoryWithContextualScopes
{
    public function createForRegistration(
        string $username,
        string $name,
        string $email,
        string $employeeType
    ): User {
        return $this->getQueryWithoutContextualScopes()->create([
            'username' => $username,
            'name' => $name,
            'email' => $email,
            'employeetype' => $employeeType,
            'publicKey' => '',
            'avatar_id' => null,
            'isRemoved' => false,
            'registration_fingerprint' => null,
        ]);
    }

    public function insert(
        string $username,
        string $name,
        string $email,
        string $employeeType
    ): User
    {
        // Update or create because the user might already exist (isRemoved = true) and we want to reuse the same record in that case.
        return $this->getQueryWithoutContextualScopes()->updateOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'email' => $email,
                'employeetype' => $employeeType,
                'publicKey' => '',
                'avatar_id' => null,
                'isRemoved' => false
            ]
        );
    }

    public function findOneByUsername(string $username, ?ScopeOverrides $scopeOverrides = null): User|null
    {
        return $this->getQuery($scopeOverrides)->where('username', $username)->first();
    }

    /**
     * Loads the user with `$username` and holds a row lock on them until the surrounding
     * transaction ends. Returns null when no such user exists — the lock then has nothing to
     * hold, and the caller has to rely on the unique index on `username` instead.
     *
     * Used by the registration flow to serialise two concurrent "complete registration"
     * requests for the same account.
     */
    public function lockOneByUsername(string $username): User|null
    {
        return $this->getQueryWithoutContextualScopes()
            ->where('username', $username)
            ->lockForUpdate()
            ->first();
    }

    public function updatePublicKey(User $user, string $publicKey): void
    {
        $user->update(['publicKey' => $publicKey]);
    }

    public function prepareForRegistration(
        User $user,
        string $name,
        string $email,
        string $employeeType
    ): User {
        $user->update([
            'name' => $name,
            'email' => $email,
            'employeetype' => $employeeType,
            'publicKey' => '',
            'avatar_id' => null,
            'isRemoved' => false,
            'registration_fingerprint' => null,
        ]);

        return $user;
    }

    public function updateRegistrationFingerprint(User $user, string $fingerprint): void
    {
        $user->update(['registration_fingerprint' => $fingerprint]);
    }

    public function findHawki(): User
    {
        return $this->getQueryWithoutContextualScopes()->findOrFail(1);
    }

    /**
     * Finds active human users (app accounts excluded) whose name, username or email matches the given term.
     * @param string $term The term to search for.
     * @param int $limit The maximum number of users to search.
     * @return Collection<int, User>
     */
    public function searchRoomInviteMembers(string $term, int $limit = 5): Collection
    {
        return $this->getQueryWithoutContextualScopes()
            ->where('isRemoved', false)
            ->where(function (Builder $query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            })
            ->where('employeetype', '!=', ExtApp::EMPLOYEE_TYPE_APP)
            ->take($limit)
            ->get();
    }

    public const SEARCH_MIN_LENGTH = 3;
    public const SEARCH_LIMIT = 5;

    /**
     * Substring match against name, username and email — the share dialog's
     * user lookup. Runs without contextual scopes: sharing must find users
     * the sharer has no other relation to yet; removed and system/app
     * accounts are never searchable.
     *
     * Terms shorter than {@see self::SEARCH_MIN_LENGTH} match nothing, LIKE
     * wildcards in the term are matched literally, and at most
     * {@see self::SEARCH_LIMIT} users are returned. The cap is applied by
     * pre-selecting ids, because JSON:API pagination overrides a plain limit().
     */
    public function filterBySearchTerm(Builder $query, string $term): Builder
    {
        $query->withoutGlobalScopes();

        $term = trim($term);
        if (mb_strlen($term) < self::SEARCH_MIN_LENGTH) {
            return $query->whereRaw('1 = 0');
        }

        $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . '%';

        $ids = (clone $query)
            ->where(static function (Builder $q) use ($pattern): void {
                $q->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("username LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern]);
            })
            ->where('isRemoved', false)
            ->whereNotIn('employeetype', ['system', 'app'])
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT)
            ->pluck('id');

        return $query->whereKey($ids);
    }

    /**
     * Which of the given ids refer to a human user eligible as a share
     * target: not removed, no system/app account.
     *
     * @param list<int> $userIds
     * @return list<int>
     */
    public function findShareableIds(array $userIds): array
    {
        if ([] === $userIds) {
            return [];
        }

        return $this->getQueryWithoutContextualScopes()
            ->where('isRemoved', false)
            ->whereNotIn('employeetype', ['system', 'app'])
            ->whereKey($userIds)
            ->pluck('id')
            ->all();
    }
}
