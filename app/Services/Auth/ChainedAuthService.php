<?php
declare(strict_types=1);


namespace App\Services\Auth;


use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithCredentialsInterface;
use App\Services\Auth\Contract\AuthServiceWithLogoutRedirectInterface;
use App\Services\Auth\Contract\AuthServiceWithPostProcessingInterface;
use App\Services\Auth\Exception\AuthFailedException;
use App\Services\Auth\Value\AuthenticatedUserInfo;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An authentication service that chains multiple authentication services together.
 * It tries to authenticate using each service in order until one succeeds or all fail.
 */
class ChainedAuthService implements AuthServiceInterface,
    AuthServiceWithCredentialsInterface,
    AuthServiceWithLogoutRedirectInterface,
    AuthServiceWithPostProcessingInterface
{
    /**
     * @var array<AuthServiceInterface> $services
     */
    private array $services;

    private bool $usingCredentials = false;

    /**
     * The service that authenticated the current request; post-processing hooks are forwarded to it.
     */
    private ?AuthServiceInterface $authenticatedService = null;

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
                $this->authenticatedService = $service;
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

    /**
     * @inheritDoc
     */
    public function afterLoginWithUser(User $user, Request $request): Response|null
    {
        $service = $this->resolveAuthenticatedService($request);

        return $service instanceof AuthServiceWithPostProcessingInterface
            ? $service->afterLoginWithUser($user, $request)
            : null;
    }

    /**
     * @inheritDoc
     */
    public function afterLoginWithoutUser(AuthenticatedUserInfo $userInfo, Request $request): Response|null
    {
        $service = $this->resolveAuthenticatedService($request);

        return $service instanceof AuthServiceWithPostProcessingInterface
            ? $service->afterLoginWithoutUser($userInfo, $request)
            : null;
    }

    /**
     * The service that authenticated this login: the one remembered in this instance, or otherwise
     * the one recorded in the session (e.g. after an external redirect round trip).
     */
    private function resolveAuthenticatedService(Request $request): ?AuthServiceInterface
    {
        if ($this->authenticatedService !== null) {
            return $this->authenticatedService;
        }

        $authenticatedService = $request->session()->get(self::SESSION_SERVICE);
        if (!is_string($authenticatedService)) {
            return null;
        }

        foreach ($this->services as $service) {
            if ($service::class === $authenticatedService) {
                return $service;
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
