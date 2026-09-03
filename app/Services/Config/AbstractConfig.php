<?php

declare(strict_types=1);

namespace App\Services\Config;

use App\Services\System\Plugins\PluginAwareTrait;
use App\Utils\Casts\AbstractCastableObject;

/**
 * Base class for all HAWKI domain configuration objects.
 *
 * Each concrete subclass must implement a static `make()` method that constructs and returns
 * an instance of that config. The method is invoked through {@see ConfigService::get()} via
 * {@see \App\Services\System\Container\ServiceLocator::call()}, so its parameters are resolved
 * from the Laravel container — constructor injection is supported.
 *
 * Configs are loaded once and cached by class name for the lifetime of the request. They are
 * registered in the container by {@see \App\Providers\ConfigServiceProvider}, which means
 * subclasses can also be injected directly via constructor injection in services:
 *
 * ```php
 * // Option 1 — inject the config class directly (resolved via ConfigServiceProvider)
 * class MyService
 * {
 *     public function __construct(private readonly MyConfig $config) {}
 * }
 *
 * // Option 2 — load via ConfigService (useful when the config class is not known statically)
 * class MyService
 * {
 *     public function __construct(private readonly ConfigService $configs) {}
 *
 *     public function doSomething(): void
 *     {
 *         $value = $this->configs->get(MyConfig::class)->myProperty;
 *     }
 * }
 * ```
 *
 * A typical config class looks like:
 *
 * ```php
 * class MyConfig extends AbstractConfig
 * {
 *     public readonly string $apiKey;
 *     public readonly int $timeout;
 *
 *     // Parameters are injected by the container — use anything registered there.
 *     public static function make(Repository $repo, MyRepository $myRepo): static
 *     {
 *         return self::fromArray([
 *             'apiKey'  => $repo->get('myservice.api_key'),
 *             'timeout' => $repo->get('myservice.timeout', 30),
 *         ]);
 *     }
 * }
 * ```
 *
 * @api
 *
 * @see ConfigService
 * @see AbstractCastableObject for property hydration and serialization
 */
abstract class AbstractConfig extends AbstractCastableObject
{
    use PluginAwareTrait;

    /**
     * Returns the namespace that groups this config in the public API response.
     *
     * Derived from the owning plugin via {@see PluginAwareTrait}: every config class inside
     * the `App\` namespace belongs to the core application and resolves to `'hawki-core'`,
     * while plugin config classes resolve to their plugin's storage-safe namespace
     * (e.g. `hawk/deepl-plugin` → `'hawk-deepl-plugin'`). The method is `final` so the
     * namespace is always anchored to the owning package — plugin configs can never
     * accidentally collide with core namespaces.
     *
     * @see \App\Services\Config\Contracts\PublicConfigInterface::publicKey() for the per-config key within this namespace
     */
    final public static function namespace(): string
    {
        return static::getContainingPlugin()->getNamespace();
    }
}
