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
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

readonly class SystemContextBootingMiddleware
{
    public const string EXT_APP_TOKEN_SCOPE = 'context:external_app';
    public const string SYSTEM_CONTEXT_BOOTED_METADATA_KEY = '__hawkiSystemContextBooted';

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
            } else if (($sanctumUser->currentAccessToken() instanceof PersonalAccessToken)) {
                $tokenAbilities = $sanctumUser->currentAccessToken()->abilities;
                // Because most of our tokens are created with "abilities" = ["*"], we need to check if the token has the specific ability for external app usage type.
                // Otherwise, we assume the user is still in the "main" usage context -> only using the api endpoints
                if (in_array(self::EXT_APP_TOKEN_SCOPE, $tokenAbilities, true)) {
                    $usageType = WellKnownUsageTypes::EXTERNAL_APP;
                }
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

        // We set this metadata on the route to indicate that the system context has been booted.
        // This means that the user-aware scoping can now be applied, because the user is now available and authenticated.
        /* @see \App\Models\Scopes\Traits\ServiceLocatingScopeTrait for where this metadata is used to determine if the system context has been booted. */
        $metadata = $request->route()?->getMetadata() ?? [];
        $metadata[self::SYSTEM_CONTEXT_BOOTED_METADATA_KEY] = true;
        $request->route()?->setMetadata($metadata);

        return $next($request);
    }
}
