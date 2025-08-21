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

            // Find local user (auth_type = 'local')
            $user = User::where('username', $username)
                       ->where('auth_type', 'local')
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
     * Check if a user is a local user
     *
     * @param string $username
     * @return bool
     */
    public function isLocalUser($username)
    {
        return User::where('username', $username)
                  ->where('auth_type', 'local')
                  ->exists();
    }

    /**
     * Create a new local user
     *
     * @param array $userData
     * @param bool $needsPasswordReset - Whether user needs to reset password (for admin-created users)
     * @return User|null
     */
    public function createLocalUser($userData, $needsPasswordReset = false)
    {
        try {
            // Approval-Logik: Standardmäßig approval=false, wenn local_needapproval aktiv ist
            $needsApproval = config('auth.local_needapproval') === true;
            $approval = array_key_exists('approval', $userData)
                ? (bool)$userData['approval']
                : !$needsApproval; // Wenn approval nicht gesetzt und Approval benötigt: false, sonst true

            $user = User::create([
                'username' => $userData['username'],
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => $userData['password'], // Will be automatically hashed
                'employeetype' => $userData['employeetype'] ?? 'local',
                'auth_type' => 'local', // Explicitly set as local user
                'reset_pw' => $needsPasswordReset, // Set based on user creation method
                'publicKey' => $userData['publicKey'] ?? '',
                'avatar_id' => $userData['avatar_id'] ?? null,
                'bio' => $userData['bio'] ?? null,
                'isRemoved' => false,
                'approval' => $approval,
            ]);

            return $user;
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
                       ->where('auth_type', 'local')
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
        return User::where('auth_type', 'local')
                  ->where('isRemoved', false)
                  ->get();
    }

    /**
     * Get all external users (not local)
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getExternalUsers()
    {
        return User::where('auth_type', '!=', 'local')
                  ->where('isRemoved', false)
                  ->get();
    }
}
