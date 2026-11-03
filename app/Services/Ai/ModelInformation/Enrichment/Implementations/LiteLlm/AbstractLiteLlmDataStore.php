<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm;


use App\Services\Ai\Providers\Values\AiProviderProxy;
use Psr\Log\LoggerInterface;

/**
 * Base class for stores that provide {@see LiteLlmModelData} objects indexed by model ID.
 *
 * Subclasses supply the provider's raw model list (from a live API or a static file);
 * this class handles provider-name resolution, model-ID matching, and merging of multiple
 * hits into a single consolidated record.
 *
 * The parsed per-provider model list is memoized in memory, keyed by the resolved LiteLLM
 * provider name, so repeated lookups do not re-read and re-parse the underlying source
 * (the multi-megabyte cached catalog or the static provider file) for every single model.
 * Freshness is delegated to the subclass: each memo carries an anchor snapshot taken via
 * {@see self::createMemoAnchor()} and stays usable until {@see self::isMemoizedProviderListValid()}
 * rejects it. Failed loads are never memoized, so lookups keep retrying as before.
 *
 * @see LiteLlmApiDataStore for the live-API implementation with caching.
 * @see StaticLiteLlmDataStore for the offline file-based implementation.
 */
abstract class AbstractLiteLlmDataStore
{
    /** @var array<string, array{list: array<LiteLlmModelData>, anchor: array<string, mixed>}> Parsed model lists by LiteLLM provider name. */
    private array $memoizedProviderLists = [];

    public function __construct(
        protected readonly LoggerInterface                       $logger,
        protected readonly LiteLlmDriverNameProviderNameMapping $nameMapping
    )
    {
    }

    /**
     * Looks up a model by ID within the provider's catalog.
     *
     * When multiple entries match the same model ID (e.g. a model listed under several
     * aliases), they are merged into a single {@see LiteLlmModelData} via
     * {@see LiteLlmModelData::mergeWith()}. Returns null when no match is found.
     *
     * Throws on failure to load the provider catalog so the caller can decide
     * whether to retry or fall back.
     */
    public function getModelInformation(
        AiProviderProxy $provider,
        string          $modelId
    ): LiteLlmModelData|null
    {
        try {
            $modelInformationList = $this->getMemoizedProviderModelInformationList($provider);
        } catch (\Throwable $e) {
            $this->logger->error('Error fetching model information', [
                'provider_id' => $provider->provider_id,
                'provider_adapter_key' => $provider->adapter_key,
                'model_id' => $modelId,
                'exception' => $e,
            ]);
            throw $e;
        }

        /** @var LiteLlmModelData[] $candidates */
        $candidates = [];
        foreach ($modelInformationList as $modelInformation) {
            if ($modelInformation->modelIdMatches($modelId)) {
                $candidates[] = $modelInformation;
            }
        }

        if (empty($candidates)) {
            return null;
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        // Collapse multiple candidates into a single LiteLlmModelInformation object by merging them
        $first = array_shift($candidates);
        foreach ($candidates as $candidate) {
            $first = $first->mergeWith($candidate);
        }

        return $first;
    }

    /**
     * Returns all raw model data records available for the given provider.
     *
     * @return iterable<LiteLlmModelData>
     */
    abstract protected function getProviderModelInformationList(AiProviderProxy $provider): iterable;

    /**
     * Snapshot of the underlying source's state, stored alongside a freshly built memo.
     *
     * Subclasses with freshness semantics capture whatever identifies the state the list
     * was parsed from (e.g. a timestamp). The default anchor suits immutable sources,
     * whose state never changes.
     *
     * @return array<string, mixed>
     */
    protected function createMemoAnchor(): array
    {
        return [];
    }

    /**
     * Whether a memoized list built on the given anchor may still be served.
     *
     * The default is unconditional: memos stay valid for the lifetime of this instance,
     * which suits immutable sources (e.g. static files deployed with the application).
     *
     * @param array<string, mixed> $anchor Anchor captured via {@see self::createMemoAnchor()}.
     */
    protected function isMemoizedProviderListValid(array $anchor): bool
    {
        return true;
    }

    /** Discards all memoized provider model lists (e.g. when the underlying cache is evicted). */
    protected function clearMemoizedProviderLists(): void
    {
        $this->memoizedProviderLists = [];
    }

    /**
     * Returns the provider's parsed model list, memoized by LiteLLM provider name.
     *
     * While the memo's anchor is still valid ({@see self::isMemoizedProviderListValid()}),
     * the underlying source is not touched again; an invalid or missing memo triggers
     * exactly one fresh load whose result replaces the memo. Loads that throw are not
     * memoized, so every lookup retries as before.
     *
     * @return array<LiteLlmModelData>
     */
    private function getMemoizedProviderModelInformationList(AiProviderProxy $provider): array
    {
        $providerName = $this->nameMapping->getProviderNameFromProxy($provider);

        $memo = $this->memoizedProviderLists[$providerName] ?? null;
        if ($memo !== null && $this->isMemoizedProviderListValid($memo['anchor'])) {
            return $memo['list'];
        }

        // Materialize once: subclasses may yield lazily, but the memo must be reusable
        $list = [...$this->getProviderModelInformationList($provider)];

        $this->memoizedProviderLists[$providerName] = [
            'list'   => $list,
            'anchor' => $this->createMemoAnchor(),
        ];

        return $list;
    }

    /**
     * Parses and validates a stream of raw model records into {@see LiteLlmModelData}
     * objects, lazily, so raw records and parsed objects never coexist in full.
     *
     * Each entry must be an associative array with at least `id` (string) and `provider`
     * (string matching the resolved provider name). Invalid or mismatched entries are skipped
     * with an error log.
     *
     * @param iterable<mixed> $raw Raw records from the API response or a static file.
     * @return iterable<int, LiteLlmModelData>
     */
    protected function loadModelDataList(
        AiProviderProxy $provider,
        iterable        $raw
    ): iterable
    {
        $providerName = $this->nameMapping->getProviderNameFromProxy($provider);

        $providerLogData = [
            'provider_id' => $provider->provider_id,
            'provider_name' => $providerName,
            'provider_adapter_key' => $provider->adapter_key
        ];

        foreach ($raw as $modelData) {
            if (!is_array($modelData)) {
                $this->logger->error('Model data is not an array', [
                    ...$providerLogData,
                    'actual_type' => get_debug_type($modelData),
                ]);
                continue;
            }
            if (!isset($modelData['id']) || !is_string($modelData['id'])) {
                $this->logger->error('Model data is missing a valid "id" field', [
                    ...$providerLogData,
                    'model_data' => $modelData,
                ]);
                continue;
            }
            if (!isset($modelData['provider']) || $modelData['provider'] !== $providerName) {
                $this->logger->error('Model data has an unexpected "provider" field', [
                    ...$providerLogData,
                    'model_provider' => $modelData['provider'] ?? null,
                ]);
                continue;
            }
            yield LiteLlmModelData::fromApiData($modelData);
        }
    }
}
