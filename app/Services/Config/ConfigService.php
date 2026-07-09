<?php
declare(strict_types=1);


namespace App\Services\Config;

use App\Services\System\Container\ServiceLocator;
use Illuminate\Config\Repository;
use Illuminate\Container\Attributes\Singleton;

/**
 * Central access point for domain config objects.
 *
 * Config objects (subclasses of {@see AbstractConfig}) are created on first access via their
 * static `make()` method and cached by class name for the rest of the request. The `make()`
 * call is dispatched through {@see ServiceLocator::call()}, so its parameters are resolved
 * by the Laravel container — all registered bindings are available as injection targets.
 * The Laravel `Repository` is always available as `$repo` in `make()` methods.
 *
 * {@see \App\Providers\ConfigServiceProvider} also registers each `AbstractConfig` subclass
 * with `$app->instance()` on first resolution, so configs can be injected directly without
 * going through this service:
 *
 * ```php
 * // Preferred when the config class is known at compile time
 * public function __construct(private readonly MyConfig $myConfig) {}
 *
 * // Required when the class is only known at runtime
 * public function load(string $configClass): AbstractConfig
 * {
 *     return $this->configService->get($configClass);
 * }
 * ```
 *
 * @api
 */
#[Singleton]
class ConfigService
{
    /** @var array<class-string<AbstractConfig>, AbstractConfig> */
    private array $map = [];

    public function __construct(
        private readonly ServiceLocator $serviceLocator,
        private readonly Repository     $repo
    )
    {
    }

    /**
     * Returns the config instance for the given class, creating it on first access.
     *
     * The instance is cached by class name — repeated calls with the same class return the
     * exact same object. The config is built by calling `$configClass::make()` through the
     * {@see ServiceLocator}, which resolves its parameters from the container and always
     * provides the Laravel {@see Repository} as the `$repo` argument.
     *
     * @throws \InvalidArgumentException when $configClass does not extend {@see AbstractConfig}
     *                                   or does not declare a static `make()` method
     */
    public function get(string $configClass): AbstractConfig
    {
        if (isset($this->map[$configClass])) {
            return $this->map[$configClass];
        }

        if (!is_subclass_of($configClass, AbstractConfig::class)) {
            throw new \InvalidArgumentException("Invalid config class: " . $configClass);
        }

        if (!method_exists($configClass, 'make')) {
            throw new \InvalidArgumentException("Config class must have a static make method: " . $configClass);
        }

        return $this->map[$configClass] = $this->serviceLocator->call(
            ['configService.make', $configClass],
            $configClass . '::make',
            ['repo' => $this->repo]
        );
    }
}
