<?php
declare(strict_types=1);


namespace App\Services\System\UsageTypes\Contracts;


/**
 * Defines the built-in usage type identifiers for HAWKI and provides a helper to
 * normalise the legacy shorthand accepted by several public API methods.
 *
 * A "usage type" is a string token that identifies the context in which the system
 * is currently being used. It controls which AI models, system prompts, and other
 * resources are made available. {@see UserContext} holds the active usage type for
 * the current request; listeners react to changes via {@see Events\UsageTypeChangedEvent}.
 *
 * Usage example — resolving available models for a given context:
 *
 * ```php
 * // Retrieve models for the main app (default):
 * $aiService->getAvailableModels();
 *
 * // Retrieve models enabled for external clients (shorthand bool):
 * $aiService->getAvailableModels(true);
 *
 * // Retrieve models for a custom usage type by its identifier string:
 * $aiService->getAvailableModels('my-custom-type');
 *
 * // Resolve the canonical usage type string yourself:
 * $type = WellKnownUsageTypes::fromExternalOrUsageType($rawValue);
 * ```
 *
 * @see UserContext        The singleton that stores the active usage type per request.
 * @see SystemContextBootingMiddleware Sets the usage type for the current HTTP request via route middleware.
 */
interface WellKnownUsageTypes
{
    /**
     * The main HAWKI interface. This is the default usage type when none is specified.
     */
    public const MAIN_APP = 'main';

    /**
     * An external client integration (e.g. a third-party app connecting via the external API).
     */
    public const EXTERNAL_APP = 'external';
}
