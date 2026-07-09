<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Values;


use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;

/**
 * Namespace interface that groups the well-known keys stored inside a provider's `settings` JSON column.
 *
 * These keys are consumed by adapter implementations when building the driver or when handling
 * per-request options.  Using the constants instead of raw strings prevents typo-driven bugs and
 * makes it easy to locate all read sites via IDE references.
 *
 * Usage:
 * ```php
 * // Reading model-level parameter overrides from the provider settings:
 * $parameters = $provider->settings->get(WellKnownProviderSettings::MODEL_PARAMETERS, []);
 *
 * // Reading adapter-specific settings (e.g. AWS region):
 * $adapterSettings = $provider->settings->get(WellKnownProviderSettings::ADAPTER, []);
 * $region = $adapterSettings['region'] ?? 'us-east-1';
 * ```
 */
interface WellKnownProviderSettings
{
    /**
     * An array of additional parameters to pass to the model, such as temperature, max_tokens, etc.
     * The model specific configuration will always win over the provider configuration.
     */
    public const string MODEL_PARAMETERS = 'model_parameters';

    /**
     * Additional settings for the {@see ProviderAdapterInterface} implementation, such as version, region, inference_provider, etc.
     * These settings are not passed to the model, but may be used by the adapter factory to determine which adapter to instantiate or how to configure it.
     */
    public const string ADAPTER = 'adapter';
}
