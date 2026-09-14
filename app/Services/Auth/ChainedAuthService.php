<?php
declare(strict_types=1);


namespace App\Services\Auth;


use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithCredentialsInterface;
use App\Services\Auth\Contract\AuthServiceWithLogoutRedirectInterface;
use App\Services\Auth\Exception\AuthFailedException;
use App\Services\Auth\Value\AuthenticatedUserInfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An authentication service that chains multiple authentication services together.
 * It tries to authenticate using each service in order until one succeeds or all fail.
 */
class ChainedAuthService implements AuthServiceInterface,
    AuthServiceWithCredentialsInterface,
    AuthServiceWithLogoutRedirectInterface
{
    /**
     * @var array<AuthServiceInterface> $services
     */
    private array $services;

    private bool $usingCredentials = false;

    private const string SESSION_SERVICE = 'auth.chained_service';

    public function __construct(
        AuthServiceInterface ...$services
    )
    {
        $this->services = $services;
    }

    /**
     * @inheritDoc
     */
    public function useCredentials(string $username, string $password): void
    {
        $this->usingCredentials = true;
        foreach ($this->services as $service) {
            if ($service instanceof AuthServiceWithCredentialsInterface) {
                $service->useCredentials($username, $password);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function forgetCredentials(): void
    {
        foreach ($this->services as $service) {
            if ($service instanceof AuthServiceWithCredentialsInterface) {
                $service->forgetCredentials();
            }
        }
        $this->usingCredentials = false;
    }

    /**
     * @inheritDoc
     */
    public function authenticate(Request $request): AuthenticatedUserInfo|Response
    {
        foreach ($this->services as $service) {
            if (($service instanceof AuthServiceWithCredentialsInterface) !== $this->usingCredentials) {
                continue;
            }

            try {
                $result = $service->authenticate($request);
                $request->session()->put(self::SESSION_SERVICE, $service::class);

                return $result;
            } catch (AuthFailedException) {
                // Try the next service, keep the exception for debugging purposes
            }
        }

        throw new AuthFailedException(
            'All authentication services failed to authenticate the user'
        );
    }

    /**
     * @inheritDoc
     */
    public function getLogoutResponse(Request $request): ?RedirectResponse
    {
        $authenticatedService = $request->session()->get(self::SESSION_SERVICE);
        foreach ($this->services as $service) {
            if (is_string($authenticatedService) && $service::class !== $authenticatedService) {
                continue;
            }
            if ($service instanceof AuthServiceWithLogoutRedirectInterface) {
                $response = $service->getLogoutResponse($request);
                if ($response !== null) {
                    return $response;
                }
            }
        }

        return null;
    }

    public function supportsRedirectAuthentication(): bool
    {
        return collect($this->services)
            ->contains(fn(AuthServiceInterface $service) => !$service instanceof AuthServiceWithCredentialsInterface);
    }
}
