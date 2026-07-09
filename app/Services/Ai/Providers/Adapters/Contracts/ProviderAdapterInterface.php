<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Contracts;


use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Storage\Interfaces\FileInterface;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Providers\Provider as Driver;
use Laravel\Ai\Providers\Tools\ProviderTool;

/**
 * Defines the contract every AI provider adapter must fulfil.
 *
 * A provider adapter is the bridge between a concrete external AI service
 * (OpenAI, Anthropic, Ollama, …) and HAWKI's generic agent/model infrastructure.
 * Implementations are registered in {@see \App\Services\Ai\Providers\Adapters\ProviderAdapterRegistry}
 * and resolved at runtime against a persisted {@see AiProvider} record.
 *
 * Responsibilities:
 * - Instantiate the framework-level Laravel AI driver for a given provider configuration.
 * - Enumerate the models available from the provider.
 * - Report which models are reachable (online status check).
 * - Optionally expose provider-native tool factories (e.g. built-in web search).
 * - Expose optional human-readable labels for the UI.
 *
 * @see \App\Services\Ai\Providers\Adapters\AbstractProviderAdapter for the base implementation
 *      that provides no-op defaults for optional methods.
 * @see \App\Services\Ai\Providers\Adapters\ProviderAdapterRegistry for registration and lookup.
 */
interface ProviderAdapterInterface
{
    /**
     * Returns a translation label for the provider's display name, or null to use the
     * stored name from the database.
     */
    public function getNameLabel(): string|null;

    /**
     * Returns a translation label for the provider's description shown in the UI, or null
     * when no description should be displayed.
     */
    public function getDescriptionLabel(): string|null;

    /**
     * Creates the framework-level Laravel AI driver for the given provider and its configuration.
     *
     * The {@see DriverFactory} handles config merging and container resolution; adapters
     * should call {@see DriverFactory::make()} with the provider-specific key and settings.
     */
    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver;

    /**
     * Returns driver-level options to merge into the agent request for this provider.
     *
     * Called just before an agent request is dispatched. Return an empty array when the
     * provider requires no extra options.
     */
    public function getAdditionalDriverOptions(Agent $agent, AgentRequestContext $context): array;

    /**
     * Returns whether the provider can accept the given file as an inline attachment.
     *
     * When false, HAWKI may fall back to embedding the file content as text instead of
     * attaching it natively.
     */
    public function supportsFileAsAttachment(FileInterface $file): bool;

    /**
     * Returns the models available from this provider, hydrated as unsaved {@see AiModel} instances.
     *
     * Implementations typically query the provider's REST API and transform each raw entry into
     * an {@see AiModel} via {@see \App\Services\Ai\ModelInformation\Enrichment\ModelInfoEnrichingTrait}.
     *
     * @return Collection<int, AiModel>
     */
    public function getModels(AiProviderProxy $provider): Collection;

    /**
     * Probes the provider and marks each known model as online or offline in the status collection.
     *
     * The default implementation in {@see \App\Services\Ai\Providers\Adapters\AbstractProviderAdapter}
     * marks every model returned by {@see getModels()} as online. Override when the provider exposes a
     * dedicated health or availability endpoint.
     */
    public function checkModelStatus(
        AiModelOnlineStatusCollection $statusCollection,
        AiModelDemandCollection       $demandCollection,
        AiProviderProxy               $provider
    ): void;

    /**
     * Returns a factory closure for a provider-native tool matching the given capability key,
     * or null when the provider does not natively support that capability.
     *
     * The returned closure is called with the current {@see AgentRequestContext} and the tool's
     * settings array to produce a framework {@see ProviderTool} instance (e.g. OpenAI's built-in
     * web-search tool). Return null to signal that HAWKI should use its own tool implementation.
     *
     * @param string $capability One of the {@see \App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities} constants.
     * @return (\Closure(AgentRequestContext $context, array $toolSettings):ProviderTool)|null
     */
    public function getNativeToolFactoryForCapability(string $capability): \Closure|null;
}
