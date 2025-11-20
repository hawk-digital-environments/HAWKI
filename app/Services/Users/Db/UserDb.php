<?php
declare(strict_types=1);


namespace App\Services\Users\Db;


use App\Events\GuestAccountCreated;
use App\Models\User;
use App\Services\Auth\Value\Local\GuestUserRequestData;
use Illuminate\Config\Repository;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

readonly class UserDb
{
    public function __construct(
        private LoggerInterface $logger,
        private Repository      $config
    )
    {
    }

    /**
     * Creates a new local user account from guest user request data
     * @param GuestUserRequestData $data The guest user request data
     * @param bool $needsPasswordReset Whether the user needs to reset their password on first login
     * @param bool $forceApproval Whether to force account approval regardless of config (default: false if not configured otherwise)
     * @return User|null
     */
    public function createUserFromGuestUserRequest(
        GuestUserRequestData $data,
        bool                 $needsPasswordReset = false,
        bool                 $forceApproval = false
    ): User|null
    {
        try {
            $user = User::create([
                'username' => $data->username,
                'name' => $data->username,
                'email' => $data->email,
                'password' => $data->password, // Will be automatically hashed
                'employeetype' => !empty($data->employeeType) ? $data->employeeType : 'local',
                'auth_type' => 'local', // Explicitly set as local user
                'reset_pw' => $needsPasswordReset, // Set based on user creation method
                'publicKey' => '',
                'avatar_id' => null,
                'bio' => null,
                'isRemoved' => false,
                'approval' => $forceApproval || $this->config->get('auth.local_needapproval') === true,
            ]);

            $this->logger->debug('Created local user "' . $user->username . '" for guest request', [
                'username' => $user->username,
                'email' => $user->email,
                'employeetype' => $user->employeetype,
                'auth_type' => $user->auth_type,
                'reset_pw' => $user->reset_pw,
            ]);

            GuestAccountCreated::dispatch($user);

            return $user;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create local user: ' . $e->getMessage(), [
                'username' => $data->username,
                'email' => $data->email,
                'employeetype' => $data->employeeType,
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Update local user password
     *
     * @param string|User $user Either the user object to update, or the username of the user
     * @param string $newPassword The new password to set
     *
     * @return bool True on success, false on failure. Will be false, if the user was not found, or there was none with the "local" auth type.
     */
    public function updatePassword(string|User $user, string $newPassword): bool
    {
        try {
            if ($user instanceof User) {
                if ($user->auth_type !== 'local') {
                    return false;
                }
            } else {
                $user = User::where('username', $user)
                    ->where('auth_type', 'local')
                    ->first();
                
                if (!$user) {
                    return false;
                }
            }

            $user->password = $newPassword; // Will be automatically hashed

            return $user->save();

        } catch (\Throwable $e) {
            $this->logger->error('Failed to update local user password: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Get all local users
     *
     * @return \Illuminate\Database\Eloquent\Collection<User>
     */
    public function getLocalUsers(): Collection
    {
        return User::where('auth_type', 'local')
            ->where('isRemoved', false)
            ->get();
    }

    /**
     * Get all external users (not local)
     *
     * @return \Illuminate\Database\Eloquent\Collection<User>
     */
    public function getExternalUsers(): Collection
    {
        return User::where('auth_type', '!=', 'local')
            ->where('isRemoved', false)
            ->get();
    }
}
