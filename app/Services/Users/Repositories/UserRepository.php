<?php
declare(strict_types=1);


namespace App\Services\Users\Repositories;


use App\Models\User;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;
use Illuminate\Database\Eloquent\Builder;

class UserRepository extends AbstractRepositoryWithContextualScopes
{
    public const SEARCH_MIN_LENGTH = 3;
    public const SEARCH_LIMIT = 5;

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

    public function findHawki(): User
    {
        return $this->getQueryWithoutContextualScopes()->findOrFail(1);
    }

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
