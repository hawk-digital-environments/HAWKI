<?php
declare(strict_types=1);


namespace App\Services\Ai\LaravelAi;


use App\Services\Ai\Exceptions\InvalidAiManagerException;
use App\Services\Ai\LaravelAi\Values\ProviderDriverPortal;
use App\Utils\DecoratorTrait;
use Laravel\Ai\AiManager;

/**
 * Extends the Laravel AiManager to support two HAWKI-specific requirements:
 *
 * 1. **Short-circuit driver resolution via {@see ProviderDriverPortal}**: when a
 *    caller passes a portal transfer ID as the driver name, the already-resolved
 *    {@see \Laravel\Ai\Providers\Provider} instance is returned directly from the
 *    portal without hitting the normal config-based resolution path. This avoids
 *    round-tripping through the database again to look up provider settings.
 *
 * 2. **Ephemeral per-call configuration**: {@see instanceWithConfig()} sets a
 *    temporary config array that {@see getInstanceConfig()} serves to the parent
 *    resolver, then restores the previous state — even on exception — so that the
 *    config never leaks across calls.
 *
 * The class itself is registered as a decorator of the original manager via
 * {@see DecoratorTrait::createDecoratedOf()} in the AI service provider, which
 * copies all injected properties from the original instance without calling the
 * constructor a second time.
 *
 * Getting the default instance intentionally throws because HAWKI always resolves
 * providers explicitly by name; there is no meaningful global default.
 */
class ExtendedAiManager extends AiManager
{
    use DecoratorTrait;

    private array|null $instanceConfig = null;

    /**
     * @inheritDoc
     *
     * When $name is a {@see ProviderDriverPortal} transfer ID, the pre-resolved
     * driver stored in the portal is returned immediately and the entry is consumed
     * (one-time transfer semantics). Otherwise falls through to normal Laravel
     * AiManager resolution.
     */
    public function instance($name = null)
    {
        if (is_string($name) && ProviderDriverPortal::isActiveTransferId($name)) {
            return ProviderDriverPortal::fromTransferId($name)->driver;
        }

        return parent::instance($name);
    }

    /**
     * Resolve a driver instance using the provided $config for the duration of
     * this call only.
     *
     * The config is stored temporarily so that {@see getInstanceConfig()} can
     * serve it to the parent resolver, then the previous value is restored in a
     * finally block to prevent config leakage across concurrent or nested calls.
     */
    public function instanceWithConfig($name = null, array $config = [])
    {
        $instanceConfigBackup = $this->instanceConfig;
        try {
            $this->instanceConfig = $config;

            return $this->instance($name);
        } finally {
            $this->instanceConfig = $instanceConfigBackup;
        }
    }

    /**
     * @inheritDoc
     *
     * Returns the ephemeral config set by the current {@see instanceWithConfig()}
     * call, or an empty array when no such call is active.
     */
    public function getInstanceConfig($name): array
    {
        return $this->instanceConfig ?? [];
    }

    /**
     * @inheritDoc
     *
     * Always throws because HAWKI resolves providers by explicit name only.
     * There is no meaningful single default AI provider.
     *
     * @throws InvalidAiManagerException
     */
    public function getDefaultInstance(): string
    {
        throw InvalidAiManagerException::forUnsupportedDefaultInstance();
    }
}
