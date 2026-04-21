<?php
declare(strict_types=1);


namespace App\Utils;


use Illuminate\Container\Container;
use Illuminate\Container\EntryNotFoundException;

/**
 * This is a simple service locator trait that allows classes to resolve services from the global container.
 * It also allows for setting services locally, which can be useful for testing or for overriding services in specific contexts.
 *
 * Why use this, if I have `app`?
 * While you can use the `app()` helper function to resolve services from the global container, this trait provides a more flexible approach.
 * It allows you to set services locally within the class, which can be particularly useful for testing or when you want to override certain
 * services without affecting the global container. Additionally, it abstracts away the direct dependency on the global container,
 * making your code more modular and easier to maintain.
 *
 * When should I use this?
 * Generally speaking, TRY TO AVOID USING THIS TRAIT, where you can use normal dependency injection.
 * It is always better to use constructor injection or method injection when possible, as it makes your dependencies explicit and your code easier to test.
 * This is still a service locator, and as such, it is considered an anti-pattern in most cases. However, it is still better than Laravels `app` and `resolve` helpers.
 *
 * Where should I use this?
 * Technically, there is no limitation on which class this trait can be used in.
 * HOWEVER, I would highly recommend the following guidelines:
 * - Models are stupid and should not have any dependencies. So this trait should not be used in Eloquent models.
 * - Services are smart and can have dependencies. Use normal dependency injection solutions provided by Laravel.
 * - Controllers can have dependencies, both on the constructor and on a per-method basis.
 * - ApiResources are prime example where this trait can be useful, as they often need to resolve services to transform data, but are not instantiated by the container.
 *
 * Okay, show me an example!
 * ```php
 * class UserResource extends JsonResource
 * {
 *     use ServiceLocatorTrait;
 *
 *     public function toArray(Request $request): array
 *     {
 *          $avatarStorage = $this->getServiceInstance(AvatarStorageService::class);
 *          ... Your other code here ...
 *     }
 * }
 *
 * This works the exact same way as before...
 * $array = User::resource();
 *
 * But in your test case, you can do this:
 * $userResource = new UserResource($user);
 *
 * $userResource->setFailOnMissingLocalService(true); -> This will make sure that if you forget to set a mock service,
 * it will throw an exception instead of silently using the real service from the container.
 *
 * $userResource->setService(AvatarStorageService::class, $mockAvatarStorageService);
 * $array = $userResource->toArray($request);
 * ```
 * @api
 */
trait ServiceLocatorTrait
{
    private bool $failOnMissingLocalService = false;
    private bool $checkedForTestingEnvironment = false;
    private array $services = [];

    /**
     * Mostly a helper for testing, which allows you to set a service instance for a given identifier. This can be used to inject mock services during testing.
     * @param string $id
     * @param mixed $service
     * @return void
     */
    public function setService(string $id, mixed $service): void
    {
        $this->services[$id] = $service;
    }

    /**
     * Resolves a service by its identifier. If the service is not found in the local services array, it falls back to the global container.
     *
     * @template TClass of object
     * @param class-string<TClass>|string $id
     * @return ($id is class-string<TClass> ? TClass : mixed)
     * @throws EntryNotFoundException
     */
    private function getServiceInstance(string $id): mixed
    {
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }

        if ($this->failOnMissingLocalService) {
            throw new EntryNotFoundException($id, 0);
        }

        if (!$this->checkedForTestingEnvironment) {
            // If we detect that we are running in a testing environment, we will fail on missing local services, because it is very likely that
            // the developer forgot to set a mock service for testing. This is a common mistake that can lead to tests that pass but do not actually test anything.
            // This might be patchy, but it is better than nothing. Explicitly setting "setFailOnMissingLocalService" is the recommended way here.
            if (PHP_SAPI === 'cli' && (
                    defined('PHPUNIT_COMPOSER_INSTALL')
                    || defined('__PHPUNIT_PHAR__')
                    || str_contains($_SERVER['argv'][0], 'phpunit')
                    || array_key_exists('phpunit_version', $GLOBALS)
                )) {
                $this->failOnMissingLocalService = true;
            }
            $this->checkedForTestingEnvironment = true;
        }

        return Container::getInstance()->get($id);
    }

    /**
     * This method allows you to set whether the trait should fail when a service is not found in the local services array.
     * This can be useful for testing, to ensure that you do not accidentally use real services instead of mock services.
     * @param bool $fail
     * @return self
     */
    public function setFailOnMissingLocalService(bool $fail = true): self
    {
        $this->failOnMissingLocalService = $fail;
        $this->checkedForTestingEnvironment = true;
        return $this;
    }
}
