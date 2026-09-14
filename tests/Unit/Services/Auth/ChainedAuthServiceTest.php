<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Services\Auth\ChainedAuthService;
use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithCredentialsInterface;
use App\Services\Auth\Contract\AuthServiceWithLogoutRedirectInterface;
use App\Services\Auth\Value\AuthenticatedUserInfo;
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

    private function requestWithSession(): Request
    {
        $session = new Store('test', new ArraySessionHandler(60));
        $session->start();
        $request = Request::create('/auth/redirect');
        $request->setLaravelSession($session);

        return $request;
    }
}
