<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Contracts;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use Laravel\Ai\Providers\Provider as Driver;

/**
 * Marks an adapter as capable of creating a framework-level AI driver instance.
 *
 * This interface is extracted from {@see ProviderAdapterInterface} so that driver
 * creation can be typed independently — for example, when only the driver creation
 * step needs to be contracted without pulling in the full adapter surface.
 *
 * All full adapter implementations satisfy this interface via
 * {@see ProviderAdapterInterface}, which redeclares {@see createDriver()}.
 */
interface ProviderAdapterCreatesDriverInterface
{
    /**
     * Creates and returns the framework-level Laravel AI driver for the given provider.
     *
     * Implementations call {@see DriverFactory::make()} with the appropriate driver name
     * and provider-specific configuration, letting the factory handle config merging and
     * container-based builder resolution.
     */
    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver;
}
