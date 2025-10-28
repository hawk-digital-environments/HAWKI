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
        $previousException = null;
        $previousMessages = [];
        foreach ($this->services as $service) {
            try {
                return $service->authenticate($request);
            } catch (AuthFailedException $e) {
                // Try the next service, keep the exception for debugging purposes
                $message = sprintf(
                    'Service "%s" failed: %s',
                    get_class($service),
                    $e->getMessage()
                );
                $previousMessages[] = $message;
                $previousException = new AuthFailedException($message, 0, $previousException);
            }
        }

        $errorDetails = implode(' | ', $previousMessages);

        throw new AuthFailedException(
            'All authentication services failed to authenticate the user. Errors: ' . $errorDetails,
            0,
            $previousException
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
