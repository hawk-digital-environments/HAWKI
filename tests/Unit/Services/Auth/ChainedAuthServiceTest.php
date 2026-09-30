<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Services\Auth\ChainedAuthService;
use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithCredentialsInterface;
use App\Services\Auth\Contract\AuthServiceWithLogoutRedirectInterface;
use App\Services\Auth\Contract\AuthServiceWithPostProcessingInterface;
use App\Services\Auth\Value\AuthenticatedUserInfo;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ChainedAuthService::class)]
class ChainedAuthServiceTest extends TestCase
{
    public function testCredentialAndRedirectProvidersRemainSeparateLoginChoices(): void
    {
        $credentials = new class implements AuthServiceInterface, AuthServiceWithCredentialsInterface {
            public int $calls = 0;
            public function useCredentials(string $username, string $password): void {}
            public function forgetCredentials(): void {}
            public function authenticate(Request $request): AuthenticatedUserInfo
            {
                $this->calls++;
                return new AuthenticatedUserInfo('local', 'Local User', 'local@example.test', 'guest');
            }
        };
        $redirect = new class implements AuthServiceInterface, AuthServiceWithLogoutRedirectInterface {
            public int $calls = 0;
            public function authenticate(Request $request): RedirectResponse
            {
                $this->calls++;
                return new RedirectResponse('https://idp.example.test/login');
            }
            public function getLogoutResponse(Request $request): ?RedirectResponse
            {
                return new RedirectResponse('https://idp.example.test/logout');
            }
        };
        $chain = new ChainedAuthService($credentials, $redirect);
        $request = $this->requestWithSession();

        $chain->useCredentials('local', 'password');
        self::assertInstanceOf(AuthenticatedUserInfo::class, $chain->authenticate($request));
        $chain->forgetCredentials();
        self::assertSame(1, $credentials->calls);
        self::assertSame(0, $redirect->calls);
        self::assertNull($chain->getLogoutResponse($request));

        $redirectRequest = $this->requestWithSession();
        self::assertInstanceOf(RedirectResponse::class, $chain->authenticate($redirectRequest));
        self::assertSame(1, $credentials->calls);
        self::assertSame(1, $redirect->calls);
        self::assertSame('https://idp.example.test/logout', $chain->getLogoutResponse($redirectRequest)?->getTargetUrl());
    }

    public function testPostLoginHooksReachOnlyTheServiceThatAuthenticated(): void
    {
        $local = new class implements AuthServiceInterface, AuthServiceWithCredentialsInterface, AuthServiceWithPostProcessingInterface {
            public int $hooks = 0;
            public function useCredentials(string $username, string $password): void {}
            public function forgetCredentials(): void {}
            public function authenticate(Request $request): AuthenticatedUserInfo
            {
                return new AuthenticatedUserInfo('local', 'Local User', 'local@example.test', 'guest');
            }
            public function afterLoginWithUser(User $user, Request $request): Response|null
            {
                $this->hooks++;
                return null;
            }
            public function afterLoginWithoutUser(AuthenticatedUserInfo $userInfo, Request $request): Response|null
            {
                $this->hooks++;
                return null;
            }
        };
        $external = new class implements AuthServiceInterface, AuthServiceWithPostProcessingInterface {
            public function authenticate(Request $request): AuthenticatedUserInfo
            {
                return new AuthenticatedUserInfo('external', 'External User', 'external@example.test', 'staff');
            }
            public function afterLoginWithUser(User $user, Request $request): Response|null
            {
                return new RedirectResponse('https://idp.example.test/welcome-back');
            }
            public function afterLoginWithoutUser(AuthenticatedUserInfo $userInfo, Request $request): Response|null
            {
                return new RedirectResponse('https://idp.example.test/register');
            }
        };
        $chain = new ChainedAuthService($local, $external);
        self::assertInstanceOf(AuthServiceWithPostProcessingInterface::class, $chain);

        $request = $this->requestWithSession();
        $info = $chain->authenticate($request);
        self::assertSame('external', $info->username);
        self::assertSame('https://idp.example.test/welcome-back', $chain->afterLoginWithUser(new User(), $request)?->getTargetUrl());
        self::assertSame('https://idp.example.test/register', $chain->afterLoginWithoutUser($info, $request)?->getTargetUrl());
        self::assertSame(0, $local->hooks, 'hooks are forwarded only to the authenticating service');

        // A fresh chain instance, as after an external redirect round trip, resolves the service from the session.
        $later = new ChainedAuthService($local, $external);
        self::assertSame('https://idp.example.test/welcome-back', $later->afterLoginWithUser(new User(), $request)?->getTargetUrl());

        $credentialRequest = $this->requestWithSession();
        $chain->useCredentials('local', 'password');
        $chain->authenticate($credentialRequest);
        self::assertNull($chain->afterLoginWithUser(new User(), $credentialRequest));
        self::assertSame(1, $local->hooks);

        self::assertNull((new ChainedAuthService($local, $external))->afterLoginWithUser(new User(), $this->requestWithSession()));
    }

    private function requestWithSession(): Request
    {
        $session = new Store('test', new ArraySessionHandler(60));
        $session->start();
        $request = Request::create('/auth/redirect');
        $request->setLaravelSession($session);

        return $request;
    }
}
