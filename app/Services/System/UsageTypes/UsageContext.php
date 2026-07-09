<?php
declare(strict_types=1);


namespace App\Services\System\UsageTypes;

use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use App\Services\System\UsageTypes\Events\UsageTypeChangedEvent;
use Illuminate\Container\Attributes\Singleton;

/**
 * Singleton service that tracks the active usage type for the current request.
 *
 * Different parts of the system inject this service to adjust their behaviour depending
 * on which "surface" is being served — for example, only certain AI models or storage
 * paths may be available to external client integrations vs. the main HAWKI interface.
 *
 * The active type defaults to {@see WellKnownUsageTypes::MAIN_APP} and is typically
 * set early in the request lifecycle by {@see \App\Http\Middleware\SystemContextBootingMiddleware}.
 * Any change via {@see set()} immediately dispatches a {@see Events\UsageTypeChangedEvent}
 * so listeners can react synchronously.
 *
 * Usage example — injecting and reading the context in a service:
 *
 * ```php
 * readonly class StorageService {
 *     public function __construct(private UsageContext $usageContext) {}
 *
 *     public function getBasePath(): string
 *     {
 *         return $this->usageContext->isExternalApp()
 *             ? config('storage.external_path')
 *             : config('storage.main_path');
 *     }
 * }
 * ```
 *
 * @see WellKnownUsageTypes              Built-in usage type identifier constants.
 * @see Events\UsageTypeChangedEvent     Event dispatched after every {@see set()} call.
 * @see \App\Http\Middleware\SystemContextBootingMiddleware  Sets the type per HTTP request via route middleware.
 */
#[Singleton]
class UsageContext
{
    private string $usageType = WellKnownUsageTypes::MAIN_APP;

    // -------------------------------------------------------
    // Well known usage types
    // -------------------------------------------------------

    /**
     * Returns true when the active usage type is the main HAWKI interface.
     */
    public function isMainApp(): bool
    {
        return $this->usageType === WellKnownUsageTypes::MAIN_APP;
    }

    /**
     * Returns true when the active usage type is an external client integration.
     */
    public function isExternalApp(): bool
    {
        return $this->usageType === WellKnownUsageTypes::EXTERNAL_APP;
    }

    // -------------------------------------------------------
    // Generics
    // -------------------------------------------------------

    /**
     * Returns true when the active usage type matches the given identifier.
     * Use the constants on {@see WellKnownUsageTypes} for the built-in types,
     * or pass a custom string for application-specific usage types.
     */
    public function is(string $usageType): bool
    {
        return $this->usageType === $usageType;
    }

    /**
     * Sets the active usage type and dispatches {@see Events\UsageTypeChangedEvent}.
     * Use the constants on {@see WellKnownUsageTypes} for the built-in types,
     * or pass a custom string for application-specific usage types.
     */
    public function set(string $usageType): void
    {
        if ($this->usageType !== $usageType) {
            $this->usageType = $usageType;
            UsageTypeChangedEvent::dispatch($this);
        }
    }

    /**
     * Returns the current usage type identifier string.
     */
    public function get(): string
    {
        return $this->usageType;
    }

    /**
     * Normalises a mixed "external or usage type" value into a canonical usage type string.
     *
     * Conversion rules:
     * - `string` → returned as-is, allowing callers to pass custom usage type identifiers
     * - `null`  → returns the current active usage type, allowing callers to default to the current context when no explicit value is given.
     *
     * @param string|null $value The raw value supplied by the caller.
     */
    public function getForGiven(string|null $value): string
    {
        return $value ?? $this->get();
    }
}
