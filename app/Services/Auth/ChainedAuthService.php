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
    }

    /**
     * @inheritDoc
     */
    public function authenticate(Request $request): AuthenticatedUserInfo|Response
    {
        foreach ($this->services as $service) {
            try {
                return $service->authenticate($request);
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
        foreach ($this->services as $service) {
            if ($service instanceof AuthServiceWithLogoutRedirectInterface) {
                $response = $service->getLogoutResponse($request);
                if ($response !== null) {
                    return $response;
                }
            }
        }

        return null;
    }
}
