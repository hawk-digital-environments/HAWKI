<?php
declare(strict_types=1);


namespace App\Services\Users\Repositories;


use App\Models\User;
use App\Services\Auth\Exception\RegistrationAlreadyCompletedException;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

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

    public function completeLegacyRegistration(
        string $username,
        string $name,
        string $email,
        string $employeeType
    ): User {
        try {
            return $this->completeLegacyRegistrationTransaction($username, $name, $email, $employeeType);
        } catch (QueryException $exception) {
            if (!$this->isUsernameCollision($exception)) {
                throw $exception;
            }

            return $this->completeLegacyRegistrationTransaction($username, $name, $email, $employeeType);
        }
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

    private function completeLegacyRegistrationTransaction(
        string $username,
        string $name,
        string $email,
        string $employeeType
    ): User {
        return DB::transaction(function () use ($username, $name, $email, $employeeType): User {
            $user = $this->lockOneByUsername($username);

            if ($user !== null && !$user->isRemoved) {
                throw new RegistrationAlreadyCompletedException();
            }

            if ($user === null) {
                return $this->createForRegistration($username, $name, $email, $employeeType);
            }

            return $this->prepareForRegistration($user, $name, $email, $employeeType);
        }, 3);
    }

    private function isUsernameCollision(QueryException $exception): bool
    {
        $sqlState = (string)($exception->errorInfo[0] ?? $exception->getCode());

        return in_array($sqlState, ['23000', '23505'], true)
            && str_contains(strtolower($exception->getMessage()), 'username');
    }
}
