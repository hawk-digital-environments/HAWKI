<?php
declare(strict_types=1);


namespace App\Policies\Traits;


use App\Models\User;
use App\Services\Users\UserCondition;
use Illuminate\Auth\Access\Response;

trait CommonPolicyChecksTrait
{
    /**
     * Checks if the user is authenticated.
     *
     * @param User|null $user The user to check.
     * @return bool True if the user is authenticated, false otherwise.
     */
    private function isUser(User|null $user): bool
    {
        return UserCondition::isUser($user);
    }

    /**
     * Returns an authorization response based on whether the user is authenticated.
     *
     * @param User|null $user The user to check.
     * @param string|null $message An optional message for the denial response.
     * @param int|null $code An optional code for the denial response.
     * @return Response An allow response if the user is authenticated, otherwise a deny response with the provided message and code.
     * @see isUser() for a boolean check that can be used in combination with other conditions using isUserOr() or similar methods.
     */
    private function isUserResponse(User|null $user, string|null $message = null, int|null $code = null): Response
    {
        if ($this->isUser($user)) {
            return $this->allow();
        }

        return $this->deny($message ?? 'Only authenticated users can perform this action.', $code);
    }

    /**
     * Checks if the user is an admin.
     *
     * @param User|null $user The user to check.
     * @return bool True if the user is an admin, false otherwise.
     */
    private function isAdmin(User|null $user): bool
    {
        return UserCondition::isAdmin($user);
    }

    /**
     * Returns an authorization response based on whether the user is an admin.
     *
     * @param User|null $user The user to check.
     * @param string|null $message An optional message for the denial response.
     * @param int|null $code An optional code for the denial response.
     * @return Response An allow response if the user is an admin, otherwise a deny response with the provided message and code.
     * @see isAdmin() for a boolean check that can be used in combination with other conditions using isAdminOr() or similar methods.
     */
    private function isAdminResponse(User|null $user, string|null $message = null, int|null $code = null): Response
    {
        if ($this->isAdmin($user)) {
            return $this->allow();
        }

        return $this->deny($message ?? 'Only admins can perform this action.', $code);
    }

    /**
     * Checks if the user is an admin or satisfies an additional condition.
     *
     * @param User|null $user The user to check.
     * @param \Closure(User $user): bool $additionalCheck A callable that takes a User and returns a boolean.
     * @return bool True if the user is an admin or satisfies the additional condition, false otherwise.
     */
    private function isAdminOr(User|null $user, \Closure $additionalCheck): bool
    {
        return $this->isAdmin($user) || ($user !== null && $additionalCheck($user));
    }

    /**
     * Returns an authorization response based on whether the user is an admin or satisfies an additional condition.
     *
     * @param User|null $user The user to check.
     * @param \Closure(User $user):(bool|string) $additionalCheck A callable that takes a User and returns a boolean.
     * @param string|null $message An optional message for the denial response.
     * @param int|null $code An optional code for the denial response.
     * @return Response An allow response if the user is an admin or satisfies the additional condition, otherwise a deny response with the provided message and code.
     * @see isAdminOr() for a boolean check that can be used in combination with other conditions.
     */
    private function isAdminOrResponse(User|null $user, \Closure $additionalCheck, string|null $message = null, int|null $code = null): Response
    {
        $orCheckMessage = null;
        $wrappedCheck = function (User $user) use ($additionalCheck, &$orCheckMessage) {
            $result = $additionalCheck($user);
            if (is_string($result)) {
                $orCheckMessage = $result;
                $result = false;
            }
            return $result;
        };

        if ($this->isAdminOr($user, $wrappedCheck)) {
            return $this->allow();
        }

        return $this->deny($message ?? $orCheckMessage ?? 'You do not have permission to perform this action.', $code);
    }
}
