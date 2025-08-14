<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\Log;

class LocalAuthService
{
    /**
     * Authenticate local user with username and password
     *
     * @param string $username
     * @param string $password
     * @return array|null User information array if authenticated, null if failed
     */
    public function authenticate($username, $password)
    {
        try {
            // Check if username or password is empty
            if (!$username || !$password) {
                return null;
            }

            // Find local user (has password field filled)
            $user = User::where('username', $username)
                       ->whereNotNull('password') // Only local users have passwords
                       ->where('isRemoved', false)
                       ->first();

            if (!$user) {
                return null;
            }

            // Verify password
            if (!Hash::check($password, $user->password)) {
                return null;
            }

            // Return user information in same format as other auth services
            return [
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'employeetype' => $user->employeetype ?? 'local',
            ];

        } catch (Exception $e) {
            Log::error('LocalAuth authentication failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if a user is a local user (has password)
     *
     * @param string $username
     * @return bool
     */
    public function isLocalUser($username)
    {
        return User::where('username', $username)
                  ->whereNotNull('password')
                  ->exists();
    }

    /**
     * Create a new local user
     *
     * @param array $userData
     * @return User|null
     */
    public function createLocalUser($userData)
    {
        try {
            return User::create([
                'username' => $userData['username'],
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => $userData['password'], // Will be automatically hashed
                'employeetype' => $userData['employeetype'] ?? 'local',
                'publicKey' => $userData['publicKey'] ?? '',
                'avatar_id' => $userData['avatar_id'] ?? null,
                'bio' => $userData['bio'] ?? null,
                'isRemoved' => false,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to create local user: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update local user password
     *
     * @param string $username
     * @param string $newPassword
     * @return bool
     */
    public function updatePassword($username, $newPassword)
    {
        try {
            $user = User::where('username', $username)
                       ->whereNotNull('password')
                       ->first();

            if (!$user) {
                return false;
            }

            $user->password = $newPassword; // Will be automatically hashed
            return $user->save();

        } catch (Exception $e) {
            Log::error('Failed to update local user password: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all local users
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLocalUsers()
    {
        return User::whereNotNull('password')
                  ->where('isRemoved', false)
                  ->get();
    }

    /**
     * Get all external users (no password)
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getExternalUsers()
    {
        return User::whereNull('password')
                  ->where('isRemoved', false)
                  ->get();
    }
}
