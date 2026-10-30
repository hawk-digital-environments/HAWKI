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
     * Finds active human users (app accounts excluded) whose name, username or email matches the given term.
     * @param string $term The term to search for.
     * @param int $limit The maximum number of users to return.
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
}
