<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithCredentialsInterface;
use App\Services\Auth\Contract\AuthServiceWithPostProcessingInterface;
use App\Services\Auth\Exception\AuthFailedException;
use App\Services\Auth\Util\AuthServiceWithCredentialsTrait;
use App\Services\Auth\Value\AuthenticatedUserInfo;
use Exception;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;


class LocalAuthService implements AuthServiceInterface, AuthServiceWithCredentialsInterface, AuthServiceWithPostProcessingInterface
{
    use AuthServiceWithCredentialsTrait;

    public function __construct(
        #[Config('auth.local_authentication')]
        bool                             $enabled,
        private readonly LoggerInterface $logger
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function authenticate(Request $request): AuthenticatedUserInfo|Response
    {
        try {
            if (empty($this->username) || empty($this->password)) {
                if (empty($this->username)) {
                    $this->logger->warning('LDAP authentication attempted with empty username.');
                } else {
                    $this->logger->warning("LDAP authentication attempted for user '{$this->username}' with empty password.");
                }
                throw new AuthFailedException('To authenticate, username and password must be provided.');
            }

            // Find local user (auth_type = 'local')
            $user = User::where('username', $this->username)
                ->where('auth_type', 'local')
                ->where('isRemoved', false)
                ->first();

            if (!$user) {
                throw new AuthFailedException('Invalid user or password');
            }

            // Verify password
            if (!Hash::check($this->password, $user->password)) {
                throw new AuthFailedException('Invalid user or password');
            }

            return new AuthenticatedUserInfo(
                username: $user->username,
                displayName: $user->name,
                email: $user->email,
                employeeType: $user->employeetype ?? 'local'
            );
        } catch (Exception $e) {
            throw new AuthFailedException('Local authentication failed', 500, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function afterLoginWithUser(User $user, Request $request, AuthenticatedUserInfo $userInfo): Response|null
    {
        // Check if this is a local user who needs to complete registration
        if ($user->auth_type !== 'local' || !empty($user->publicKey)) {
            return null;
        }

        // Local user who needs to complete registration process
        // This includes both admin-created users and self-service users
        $request->session()->put([
            'registration_access' => true,
            'authenticatedUserInfo' => json_encode($userInfo),
            'first_login_local_user' => true
        ]);

        return response()->json([
            'success' => true,
            'redirectUri' => '/register',
        ]);
    }

    /**
     * @inheritDoc
     */
    public function afterLoginWithoutUser(AuthenticatedUserInfo $userInfo, Request $request): Response|null
    {
        return response()->json([
            'success' => false,
            'message' => 'User account not found or deactivated.',
        ]);
    }
}
