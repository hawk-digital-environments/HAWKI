<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm;

use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Values\AiProviderProxy;

/**
 * By default, we use the {@see ProviderAdapterInterface::getDriverName()} as the LiteLlm provider name.
 * However, this mapping table will be always in the middle to ensure that we can override the LiteLlm provider name
 * for a given adapter key if needed. This is useful for cases where the LiteLlm provider name is different from the adapter driver name.
 *
 * Use this in your service provider to declare a new mapping if needed.
 *
 * @api
 */
class LiteLlmDriverNameProviderNameMapping
{
    private array $mapping = [];

    /**
     * Registers a custom LiteLLM provider name for a given adapter key.
     *
     * Use this when the adapter's driver name differs from the LiteLLM catalog provider
     * name (e.g. the adapter driver is `elevenlabs_api` but LiteLLM calls it `elevenlabs`).
     */
    public function declare(
        string $adapterKey,
        string $liteLlmProviderName
    ): self
    {
        $this->mapping[$adapterKey] = $liteLlmProviderName;
        return $this;
    }

    /**
     * Resolves a driver name to its LiteLLM provider name.
     *
     * Returns the driver name unchanged when no explicit mapping has been declared for it.
     */
    public function getProviderName(string $driverName): string
    {
        return $this->mapping[$driverName] ?? $driverName;
    }

    /**
     * Convenience wrapper: reads the driver name from the adapter of $provider and maps it.
     */
    public function getProviderNameFromProxy(AiProviderProxy $provider): string
    {
        $driverName = $provider->driver->driver();
        return $this->getProviderName($driverName);
    }
}
