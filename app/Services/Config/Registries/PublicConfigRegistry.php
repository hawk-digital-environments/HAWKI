<?php
declare(strict_types=1);


namespace App\Services\Config\Registries;

use App\Services\Config\Contracts\PublicConfigInterface;
use App\Utils\Lists\LazySingletonList;
use Illuminate\Container\Attributes\Singleton;
use Traversable;

/**
 * Registry of all config classes that should appear in the public `GET /api/v1/configs` response.
 *
 * Configs are registered by class name via {@see declare()} and instantiated lazily on first
 * iteration. The registry is extended in service providers using `$app->extend()`:
 *
 * ```php
 * // In a ServiceProvider::register()
 * $this->app->extend(
 *     PublicConfigRegistry::class,
 *     fn(PublicConfigRegistry $registry) => $registry->declare(MyConfig::class),
 * );
 * ```
 *
 * Each declared class must implement {@see PublicConfigInterface}. Iteration yields the
 * instantiated config objects in the order they were declared. Declaring the same class
 * twice is a no-op — the class map is keyed by class name.
 *
 * @implements \IteratorAggregate<PublicConfigInterface>
 */
#[Singleton]
class PublicConfigRegistry implements \IteratorAggregate
{
    /**
     * Class names of all registered public configs, keyed by class name to prevent duplicates.
     *
     * @var array<class-string<PublicConfigInterface>, class-string<PublicConfigInterface>>
     */
    private array $publicConfigClasses = [];

    public function __construct(
        /**
         * Lazy singleton cache that maps config class names to their instantiated objects.
         * Backed by {@see \App\Services\Config\ConfigService::get()} in production.
         *
         * @var LazySingletonList<class-string<PublicConfigInterface>, PublicConfigInterface>
         */
        private readonly LazySingletonList $publicConfigs
    )
    {
    }

    /**
     * Registers a config class for inclusion in the public API response.
     *
     * The class is validated immediately to implement {@see PublicConfigInterface}.
     * Registration is idempotent — declaring the same class more than once has no effect.
     *
     * @param class-string<PublicConfigInterface> $configClass
     * @throws \InvalidArgumentException when $configClass does not implement {@see PublicConfigInterface}
     */
    public function declare(string $configClass): self
    {
        if (!is_a($configClass, PublicConfigInterface::class, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Public config class %s must implement %s',
                $configClass,
                PublicConfigInterface::class));
        }

        $this->publicConfigClasses[$configClass] = $configClass;
        return $this;
    }

    /**
     * Iterates over all registered config instances in declaration order.
     *
     * Each config is instantiated via the injected {@see LazySingletonList} on first access
     * and reused on subsequent iterations.
     */
    public function getIterator(): Traversable
    {
        foreach ($this->publicConfigClasses as $configClass) {
            yield $this->publicConfigs->get($configClass);
        }
    }
}
