<?php

namespace App\Services\Auth;

use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithCredentialsInterface;
use App\Services\Auth\Exception\AuthFailedException;
use App\Services\Auth\Util\AuthServiceWithCredentialsTrait;
use App\Services\Auth\Value\AuthenticatedUserInfo;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated This service is superseded by {@see LocalAuthService}
 */
class TestAuthService implements AuthServiceInterface, AuthServiceWithCredentialsInterface
{
    use AuthServiceWithCredentialsTrait;

    private array|null $users;

    public function __construct(
        #[Config('test_users.testers', [])]
        mixed $users
    )
    {
        $this->users = is_array($users) && !empty($users) ? $users : null;
    }

    public function authenticate(Request $request): AuthenticatedUserInfo|Response
    {
        if($this->users === null){
            throw new AuthFailedException('No test users are configured.', 500);
        }

        $user = collect($this->users)->first(function ($user) {
            return $user['username'] === $this->username && $user['password'] === $this->password;
        });

        if (!$user) {
            throw new AuthFailedException('Invalid test user credentials.', 401);
        }

        return new AuthenticatedUserInfo(
            username: $user['username'],
            displayName: $user['name'],
            email: $user['email'],
            employeeType: 'tester',
        );
    }
}
