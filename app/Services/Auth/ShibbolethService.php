<?php

namespace App\Services\Auth;

use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithLogoutRedirectInterface;
use App\Services\Auth\Exception\AuthFailedException;
use App\Services\Auth\Util\AuthRedirectBuilder;
use App\Services\Auth\Util\DisplayNameBuilder;
use App\Services\Auth\Value\AuthenticatedUserInfo;
use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;


#[Singleton]
readonly class ShibbolethService implements AuthServiceWithLogoutRedirectInterface, AuthServiceInterface
{
    public function __construct(
        #[Config('shibboleth.attribute_map.username')]
        private string          $usernameAttribute,
        #[Config('shibboleth.attribute_map.email')]
        private string          $emailAttribute,
        #[Config('shibboleth.attribute_map.employeetype')]
        private string          $employeeTypeAttribute,
        #[Config('shibboleth.attribute_map.name')]
        private string          $nameAttribute,
        #[Config('shibboleth.login_path')]
        private string          $loginPath,
        #[Config('shibboleth.logout_path')]
        private string          $logoutPath,
        private LoggerInterface $logger,
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function authenticate(Request $request): AuthenticatedUserInfo|Response
    {
        try {
            $username = $this->getServerVarOrFail($request, $this->usernameAttribute);
            $this->logger->debug('Authenticated Shibboleth user', ['username' => $username]);
        } catch (\RuntimeException $e) {
            $loginRedirect = AuthRedirectBuilder::build(
                $this->loginPath,
                ['target' => 'web.auth.login']
            );

            if (!$loginRedirect) {
                throw new AuthFailedException('Shibboleth login path is not set in configuration', 500, $e);
            }

            return $loginRedirect;
        }

        try {
            $userdata = new AuthenticatedUserInfo(
                username: $username,
                displayName: DisplayNameBuilder::build(
                    definition: $this->nameAttribute,
                    valueResolver: fn(string $field) => $this->getServerVarOrFail($request, $field),
                    logger: $this->logger
                ),
                email: $this->getServerVarOrFail($request, $this->emailAttribute),
                employeeType: $this->getServerVarOrFail($request, $this->employeeTypeAttribute),
            );

            $this->logger->debug('Retrieved Shibboleth user attributes', ['userdata' => $userdata]);

            return $userdata;
        } catch (\Throwable $e) {
            throw new AuthFailedException('Failed to resolve userdata for Shibboleth auth', 500, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function getLogoutResponse(Request $request): ?RedirectResponse
    {
        return AuthRedirectBuilder::build(
            $this->logoutPath,
            ['return' => 'login']
        );
    }

    private function getServerVarOrFail(Request $request, string $var): string
    {
        $redirectVar = 'REDIRECT_' . $var;
        $value = $request->server($var) ?? $request->server('REDIRECT_' . $var) ?? null;

        if (!empty($value)) {
            return $value;
        }

        // Try to find value case-insensitively (This is rather expensive, so we log it as a warning)
        foreach ($request->server->all() as $key => $val) {
            if (Str::lower($key) === Str::lower($var) && !empty($val)) {
                $this->logger->warning("Found server variable '$key' case-insensitively for expected '$var'");
                return $val;
            }
            if (Str::lower($key) === Str::lower($redirectVar) && !empty($val)) {
                $this->logger->warning("Found server variable '$key' case-insensitively for expected '$redirectVar'");
                return $val;
            }
        }

        throw new \RuntimeException("Neither $var nor $redirectVar are set and not empty", 400);
    }
}
