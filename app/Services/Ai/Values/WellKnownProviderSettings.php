<?php
declare(strict_types=1);


namespace App\Services\Ai\Values;


use App\Services\Ai\ProviderAdapters\Contracts\ProviderAdapterInterface;

interface WellKnownProviderSettings
{
    /**
     * An array of additional parameters to pass to the model, such as temperature, max_tokens, etc.
     * The model specific configuration will always win over the provider configuration
     */
    public const MODEL_PARAMETERS = 'model_parameters';

    /**
     * Additional settings for the {@see ProviderAdapterInterface} implementation, such as version, region, inference_provider, etc.
     * These settings are not passed to the model, but may be used by the adapter factory to determine which adapter to instantiate or how to configure it.
     */
    public const ADAPTER = 'adapter';
}
