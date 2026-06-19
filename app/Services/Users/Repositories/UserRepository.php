<?php
declare(strict_types=1);


namespace App\Services\Users\Repositories;


use App\Models\User;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;

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
}
