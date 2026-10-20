<?php
declare(strict_types=1);


namespace App\Services\Users;


use App\Models\User;
use Illuminate\Http\Request;

class UserCondition
{
    /**
     * Checks if the given user is authenticated.
     *
     * @param User|Request|null $user The user or request to check.
     * @return bool True if the user is authenticated, false otherwise.
     */
    public static function isUser(User|Request|null $user): bool
    {
        if ($user instanceof Request) {
            $user = $user->user();
        }
        return $user !== null;
    }

    /**
     * Checks if the given user is an admin.
     *
     * @param User|Request|null $user The user or request to check.
     * @return bool True if the user is an admin, false otherwise.
     */
    public static function isAdmin(User|Request|null $user): bool
    {
        if ($user instanceof Request) {
            $user = $user->user();
        }
        if (!$user) {
            return false;
        }
        return $user->employeetype === 'admin';
    }

    /**
     * Checks if the given user is not an admin.
     *
     * @param User|Request|null $user The user or request to check.
     * @return bool True if the user is not an admin, false otherwise.
     */
    public static function isNonAdmin(User|Request|null $user): bool
    {
        return !self::isAdmin($user);
    }
}
