<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Exceptions\InvalidProviderConfigurationException;
use App\Services\Ai\ModelInformation\Enrichment\ModelInfoEnrichingTrait;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListClient;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Storage\Interfaces\FileInterface;
use Illuminate\Http\Client\PendingRequest;
use Laravel\Ai\Contracts\Agent;

/**
 * Base class for all built-in AI provider adapters.
 *
 * Provides sensible no-op defaults for the optional parts of {@see ProviderAdapterInterface}
 * so that concrete adapters only need to implement the mandatory methods
 * ({@see createDriver()} and {@see getModels()}) and can selectively override the rest.
 *
 * Default behaviours provided here:
 * - {@see checkModelStatus()} marks every model returned by {@see getModels()} as online —
 *   sufficient for providers that have no dedicated health endpoint.
 * - {@see getNameLabel()} and {@see getDescriptionLabel()} return null (use DB-stored strings).
 * - {@see getNativeToolFactoryForCapability()} returns null (no provider-native tool support).
 * - {@see getAdditionalDriverOptions()} returns an empty array (no extra driver options).
 * - {@see supportsFileAsAttachment()} returns true (all file types accepted).
 *
 * Also includes {@see ModelInfoEnrichingTrait} so that concrete adapters can call the
 * fill-in-the-blank enrichment helpers (e.g. {@see createNewModelInfo()}) directly.
 *
 * @see ProviderAdapterInterface for the full contract.
 */
abstract class AbstractProviderAdapter implements ProviderAdapterInterface
{
    use ModelInfoEnrichingTrait;

    /**
     * Default status check: marks every model returned by {@see getModels()} as online.
     *
     * Override in adapters that can query a dedicated provider health or availability endpoint
     * for more accurate results.
     */
    public function checkModelStatus(
        AiModelOnlineStatusCollection $statusCollection,
        AiModelDemandCollection       $demandCollection,
        AiProviderProxy               $provider
    ): void
    {
        foreach ($this->getModels($provider) as $model) {
            $statusCollection->setOnline($model->model_id);
        }
    }

    /**
     * Returns null — concrete adapters should override when a translation label is needed
     * instead of the name stored in the database.
     */
    public function getNameLabel(): string|null
    {
        return null;
    }

    /**
     * Returns null — concrete adapters should override when the UI should show a description
     * for this provider type.
     */
    public function getDescriptionLabel(): string|null
    {
        return null;
    }

    /**
     * Returns null — override in adapters whose provider exposes a native tool for the
     * requested capability (e.g. built-in web search on OpenAI).
     */
    public function getNativeToolFactoryForCapability(string $capability): \Closure|null
    {
        return null;
    }

    /**
     * Returns an empty array — override when a provider requires extra driver-level options
     * to be merged into the agent request at dispatch time.
     */
    public function getAdditionalDriverOptions(Agent $agent, AgentRequestContext $context): array
    {
        return [];
    }

    /**
     * Returns true — override when the provider cannot accept certain file types as native
     * attachments so HAWKI can fall back to text embedding instead.
     */
    public function supportsFileAsAttachment(FileInterface $file): bool
    {
        return true;
    }

    /**
     * Validates that $apiUrl is non-empty and returns it; throws otherwise.
     *
     * Required for adapters backed by a user-configured endpoint (e.g. Ollama, OpenAI-like
     * providers) where a missing URL would cause a silent or misleading HTTP failure later.
     *
     * @throws InvalidProviderConfigurationException when $apiUrl is empty.
     */
    protected function assertNonEmptyApiUrl(string|null $apiUrl, AgentRequestContext|AiProvider $context): string
    {
        if (empty($apiUrl)) {
            $provider = $context instanceof AgentRequestContext ? $context->provider : $context;
            throw InvalidProviderConfigurationException::forMissingApiUrl(
                $provider->name ?? ('Broken provider without name - ' . ($provider->id ?? 'and no id')),
                $provider->adapter_key ?? 'Missing adapter key'
            );
        }

        return $apiUrl;
    }

    /**
     * Creates a {@see ModelListClient} backed by the given pre-configured HTTP request.
     *
     * The request is wrapped in a closure so its construction is deferred until the first
     * HTTP call is actually made — auth headers and base URLs are resolved at that point.
     */
    protected function createModelListClient(PendingRequest $request): ModelListClient
    {
        return new ModelListClient(fn() => $request);
    }
}
