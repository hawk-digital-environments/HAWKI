<?php
declare(strict_types=1);


namespace App\Services\System\Container;


use App\Services\System\Container\Exceptions\ServiceLocatorException;
use Illuminate\Container\Container;

/**
 * A service locator with local overrides and PHPUnit-aware safety guards.
 *
 * Use this trait when constructor injection is unavailable — the primary use-case is API Resources,
 * which Laravel instantiates outside the container. For everything else, prefer constructor injection.
 *
 * Services registered via {@see setService()} always take precedence over the container.
 * When running inside PHPUnit, container fallback is automatically disabled so that forgetting to inject
 * a mock throws immediately rather than silently using the real service.
 *
 * Usage:
 * ```php
 * class UserResource extends JsonResource
 * {
 *     use ServiceLocatorTrait;
 *
 *     public function toArray(Request $request): array
 *     {
 *         $avatarStorage = $this->getService(AvatarStorageService::class);
 *         // ...
 *     }
 * }
 *
 * // In tests:
 * $resource = new UserResource($user);
 * $resource->useServiceContainerFallback(false); // throw instead of silently resolving from container
 * $resource->setService(AvatarStorageService::class, $mockAvatarStorage);
 * $array = $resource->toArray($request);
 * ```
 *
 * @api
 */
trait ServiceLocatorTrait
{
    private ServiceLocator|null $serviceLocator = null;
    private bool|null $serviceLocatorUsesContainerFallbackByEnv = null;
    private bool|null $serviceLocatorUsesContainerFallbackUser = null;

    /**
     * Returns the backing {@see ServiceLocator}, creating it on first access.
     */
    protected function getServiceLocator(): ServiceLocator
    {
        if ($this->serviceLocator === null) {
            $this->serviceLocator = new ServiceLocator();
        }
        return $this->serviceLocator;
    }

    /**
     * Registers a service locally, overriding whatever the container would resolve.
     * Primarily useful in tests to inject mock services without touching the global container.
     */
    public function setService(string $id, mixed $service): self
    {
        $this->getServiceLocator()->set($id, $service);
        return $this;
    }

    /**
     * Resolves a service by its identifier.
     *
     * Falls back to the global container unless container fallback has been disabled
     * (see {@see useServiceContainerFallback()}). When running inside PHPUnit and no explicit setting
     * has been applied, fallback is automatically disabled to catch missing test mocks early.
     *
     * @throws ServiceLocatorException when the service is not found locally and the container is not available.
     */
    protected function getService(string $id): mixed
    {
        if ($this->serviceLocatorUsesContainerFallbackByEnv === null) {
            $this->serviceLocatorUsesContainerFallbackByEnv = true;

            // Automatically disable container fallback when running in PHPUnit so that forgetting to register
            // a mock throws immediately. Use useServiceContainerFallback() to override explicitly.
            if (PHP_SAPI === 'cli' && (
                    defined('PHPUNIT_COMPOSER_INSTALL')
                    || defined('__PHPUNIT_PHAR__')
                    || str_contains($_SERVER['argv'][0], 'phpunit')
                    || array_key_exists('phpunit_version', $GLOBALS)
                )) {
                $this->serviceLocatorUsesContainerFallbackByEnv = false;
            }
        }

        $useContainerFallback = $this->serviceLocatorUsesContainerFallbackUser ?? $this->serviceLocatorUsesContainerFallbackByEnv;

        return $this->getServiceLocator()
            ->setContainer($useContainerFallback ? Container::getInstance() : null)
            ->get($id);
    }

    /**
     * Controls whether unresolved services fall back to the global container.
     *
     * Pass false to throw immediately when a service is not registered locally — recommended in tests
     * to catch missing mocks. Pass true to always allow container fallback. Pass null to restore the
     * default auto-detection behavior (container disabled when running inside PHPUnit).
     */
    public function useServiceContainerFallback(bool|null $useFallback = null): self
    {
        $this->serviceLocatorUsesContainerFallbackUser = $useFallback;
        return $this;
    }
}
