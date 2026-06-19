<?php

namespace App\Http\Middleware;

use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use App\Services\System\UsageTypes\UsageContext;
use App\Services\System\UserTypes\Contracts\WellKnownUserTypes;
use App\Services\System\UserTypes\UserContext;
use App\Services\System\UserTypes\Values\RegisteringUser;
use Closure;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;

readonly class SystemContextBootingMiddleware
{
    public const EXT_APP_TOKEN_SCOPE = 'context:external_app';

    public function __construct(
        private UsageContext $usageContext,
        private UserContext  $userContext,
        private AuthManager  $auth
    )
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $usageType = WellKnownUsageTypes::MAIN_APP;
        $userType = WellKnownUserTypes::GUEST;

        if (($sanctumUser = $request->user('sanctum'))
            // Sanctum users that are authenticated via a token will have a currentAccessToken() that is NOT an
            // instance of TransientToken, while users authenticated via "normal" sanctum session cookies will have
            // a currentAccessToken() that IS an instance of TransientToken. This check allows us to differentiate between
            // these two types of authentication and only apply the external app usage type to the former.
            && !($sanctumUser->currentAccessToken() instanceof TransientToken)) {
            if ($sanctumUser->employeetype === 'app') {
                $usageType = WellKnownUsageTypes::EXTERNAL_APP;
                $userType = WellKnownUserTypes::EXTERNAL_APP;
            } else if ($sanctumUser->tokenCan(self::EXT_APP_TOKEN_SCOPE)) {
                $usageType = WellKnownUsageTypes::EXTERNAL_APP;
                $userType = WellKnownUserTypes::USER;
            } else {
                $userType = WellKnownUserTypes::USER;
            }

            // Fix to ensure that the rest of the request lifecycle (e.g. authorization) uses the sanctum guard when a sanctum user is present.
            $this->auth->setDefaultDriver('sanctum');
        } else if ($request->user()) {
            $userType = WellKnownUserTypes::USER;
        } else if ($request->hasSession() && $request->session()->has('authenticatedUserInfo')) {
            $registeringUserInfo = $request->session()->get('authenticatedUserInfo');
            if (json_validate($registeringUserInfo)) {
                $registeringUserData = json_decode($registeringUserInfo, true);
                $user = new RegisteringUser(
                    username: $registeringUserData['username'] ?? '',
                    name: $registeringUserData['name'] ?? '',
                    email: $registeringUserData['email'] ?? '',
                    employeeType: $registeringUserData['employeetype'] ?? '',
                );
                $this->userContext->setRegisteringUser($user);
                $userType = WellKnownUserTypes::REGISTERING_USER;
            }
        }

        $this->usageContext->set($usageType);
        $this->userContext->set($userType);

        return $next($request);
    }
}
