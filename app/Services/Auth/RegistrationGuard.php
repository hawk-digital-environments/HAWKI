<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Auth\Exception\RegistrationAlreadyCompletedException;
use Illuminate\Database\QueryException;

readonly class RegistrationGuard
{
    public static function isAccountInitialized(?User $user): bool
    {
        return $user !== null && !$user->isRemoved && $user->publicKey !== '';
    }

    public static function assertAccountNotInitialized(?User $user): void
    {
        if (self::isAccountInitialized($user)) {
            throw new RegistrationAlreadyCompletedException();
        }
    }

    /**
     * Retry once after a concurrent registration inserts the same username.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public static function retryUsernameCollision(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (QueryException $exception) {
            $sqlState = (string)($exception->errorInfo[0] ?? $exception->getCode());
            if (!in_array($sqlState, ['23000', '23505'], true)
                || !str_contains(strtolower((string)($exception->errorInfo[2] ?? '')), 'username')) {
                throw $exception;
            }

            return $operation();
        }
    }
}
