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
 * @see LiteLlmApiDataStore for the live-API implementation with caching.
 * @see StaticLiteLlmDataStore for the offline file-based implementation.
 */
abstract readonly class AbstractLiteLlmDataStore
{
    public function __construct(
        protected LoggerInterface                      $logger,
        protected LiteLlmDriverNameProviderNameMapping $nameMapping
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
            $modelInformationList = $this->getProviderModelInformationList($provider);
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
     * @return array<LiteLlmModelData>
     */
    abstract protected function getProviderModelInformationList(AiProviderProxy $provider): array;

    /**
     * Parses and validates a raw array of model records into {@see LiteLlmModelData} objects.
     *
     * Each entry must be an associative array with at least `id` (string) and `provider`
     * (string matching the resolved provider name). Invalid or mismatched entries are skipped
     * with an error log.
     *
     * @param array<mixed> $raw Raw records from the API response or a static file.
     * @return array<LiteLlmModelData>
     */
    protected function loadModelDataList(
        AiProviderProxy $provider,
        array           $raw
    ): array
    {
        $providerName = $this->nameMapping->getProviderNameFromProxy($provider);

        $providerLogData = [
            'provider_id' => $provider->provider_id,
            'provider_name' => $providerName,
            'provider_adapter_key' => $provider->adapter_key
        ];

        $modelInformationList = [];
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
            $modelInformationList[] = LiteLlmModelData::fromApiData($modelData);
        }

        return $modelInformationList;
    }
}
