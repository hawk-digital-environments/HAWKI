<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithCredentialsInterface;
use App\Services\Auth\Exception\AuthFailedException;
use App\Services\Auth\Util\AuthServiceWithCredentialsTrait;
use App\Services\Auth\Value\AuthenticatedUserInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class LocalAuthService implements AuthServiceInterface, AuthServiceWithCredentialsInterface
{
    use AuthServiceWithCredentialsTrait;

    public function authenticate(Request $request): AuthenticatedUserInfo|Response
    {
        $user = User::withoutGlobalScopes()
            ->where('isRemoved', false)
            ->where(function ($query) {
                $query->where('username', $this->username)
                    ->orWhere('email', $this->username);
            })
            ->first();

        if (!$user || !is_string($user->local_password) || !Hash::check($this->password, $user->local_password)) {
            throw new AuthFailedException('Invalid local user credentials.', 401);
        }

        return new AuthenticatedUserInfo(
            username: $user->username,
            displayName: $user->name,
            email: $user->email,
            employeeType: $user->employeetype,
        );
    }
}
