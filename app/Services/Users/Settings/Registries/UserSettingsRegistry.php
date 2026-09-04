<?php

declare(strict_types=1);

namespace App\Services\Users\Settings\Registries;

use App\Services\Users\Exceptions\DuplicateUserSettingsKeyException;
use App\Services\Users\Exceptions\InvalidUserSettingsClassException;
use App\Services\Users\Settings\AbstractUserSettings;
use Illuminate\Container\Attributes\Singleton;

/**
 * Registry of all user-settings classes exposed through the `user-settings` JSON:API
 * resource.
 *
 * Settings classes are registered by class name via {@see declare()} and are extended
 * in service providers using `$app->extend()`:
 *
 * ```php
 * $this->app->extend(
 *     UserSettingsRegistry::class,
 *     fn(UserSettingsRegistry $registry) => $registry->declare(CoreUserSettings::class),
 * );
 * ```
 *
 * Deliberate deviation from {@see \App\Services\Config\Registries\PublicConfigRegistry}:
 * this registry stores **class names only** — no instance cache. The public-config
 * registry can cache instances because configs are global-scope; user-settings instances
 * are per-user, and caching them here would return one user's settings to another after
 * a user switch in a long-lived process. Consumers resolve instances through
 * {@see \App\Services\Users\Settings\UserSettingsService} per request, which owns a
 * correctly-keyed identity map.
 *
 * @api
 *
 * @see AbstractUserSettings
 */
#[Singleton()]
class UserSettingsRegistry
{
    /**
     * Class names of all registered settings classes, keyed by class name to prevent
     * duplicates.
     *
     * @var array<class-string<AbstractUserSettings>, class-string<AbstractUserSettings>>
     */
    private array $settingsClasses = [];

    /**
     * Registers a settings class for the public API surface.
     *
     * The class is validated immediately to extend {@see AbstractUserSettings}, and its
     * `publicKey()` must be globally unique across the registry. Registration is
     * idempotent — declaring the same class more than once has no effect.
     *
     * @param class-string<AbstractUserSettings> $settingsClass
     *
     * @throws DuplicateUserSettingsKeyException when the class's public key is already taken
     * @throws InvalidUserSettingsClassException when $settingsClass does not extend the settings base
     */
    public function declare(string $settingsClass): self
    {
        if (!is_a($settingsClass, AbstractUserSettings::class, true)) {
            throw InvalidUserSettingsClassException::forClass($settingsClass);
        }

        if (isset($this->settingsClasses[$settingsClass])) {
            return $this;
        }

        $publicKey = $settingsClass::publicKey();

        foreach ($this->settingsClasses as $registered) {
            if ($registered::publicKey() === $publicKey) {
                throw DuplicateUserSettingsKeyException::forPublicKey($publicKey, $registered, $settingsClass);
            }
        }

        $this->settingsClasses[$settingsClass] = $settingsClass;

        return $this;
    }

    /**
     * Returns all registered settings classes in declaration order.
     *
     * @return list<class-string<AbstractUserSettings>>
     */
    public function all(): array
    {
        return array_values($this->settingsClasses);
    }

    /**
     * Returns the registered settings classes grouped by their namespace.
     *
     * @return array<string, list<class-string<AbstractUserSettings>>>
     */
    public function classesByNamespace(): array
    {
        $grouped = [];

        foreach ($this->settingsClasses as $settingsClass) {
            $grouped[$settingsClass::namespace()][] = $settingsClass;
        }

        return $grouped;
    }

    /**
     * Returns the registered settings classes of one namespace — the classes behind a
     * single namespace-scoped `user-settings` JSON:API resource.
     *
     * @return list<class-string<AbstractUserSettings>>
     */
    public function classesForNamespace(string $namespace): array
    {
        return $this->classesByNamespace()[$namespace] ?? [];
    }

    /**
     * Returns all namespaces that have at least one registered settings class.
     *
     * @return list<string>
     */
    public function namespaces(): array
    {
        return array_keys($this->classesByNamespace());
    }

    /**
     * Returns all registered settings classes keyed by their public key — the shape the
     * JSON:API schema fields (one attribute per public key) are built from.
     *
     * @return array<string, class-string<AbstractUserSettings>>
     */
    public function classesByPublicKey(): array
    {
        $keyed = [];

        foreach ($this->settingsClasses as $settingsClass) {
            $keyed[$settingsClass::publicKey()] = $settingsClass;
        }

        return $keyed;
    }
}
