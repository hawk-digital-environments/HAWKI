<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm;


use Illuminate\Http\Client\Factory;
use Psr\Log\LoggerInterface;

/**
 * HTTP client for the LiteLLM model catalog (`model_prices_and_context_window.json`).
 *
 * The catalog is a single JSON object keyed by model ID; each entry names its provider in
 * `litellm_provider`. Known non-model entries ({@see self::NON_MODEL_KEYS}) are skipped.
 * Any other entry without a valid provider is unexpected and skipped with a debug log.
 * Every yielded record is normalized to carry `id` (the object key) and `provider`
 * (the `litellm_provider` value); the original `litellm_provider` key is stripped.
 *
 * The whole catalog (~3 MB) is downloaded per call, so the transfer timeout is more lenient
 * than the connect timeout. Failures are thrown because the caller falls back to static files.
 *
 * @see StaticLiteLlmDataUpdater which uses this client to regenerate the static fallback files.
 */
readonly class LiteLlmApiClient
{
    private const string API_URL = 'https://raw.githubusercontent.com/BerriAI/litellm/refs/heads/main/model_prices_and_context_window.json';

    /** Top-level catalog entries that carry schema documentation or configuration instead of model data. */
    private const array NON_MODEL_KEYS = ['sample_spec', 'fallback_generalizations'];

    public function __construct(
        readonly private Factory          $http,
        readonly private LoggerInterface $logger,
    )
    {
    }

    /** Returns the URL the catalog is downloaded from. */
    public function getUrl(): string
    {
        return self::API_URL;
    }

    /**
     * Downloads the LiteLLM model catalog and yields each model record as a normalized array.
     *
     * @param string|null $providerName When set, only records for this LiteLLM provider are returned.
     */
    public function fetchData(string|null $providerName = null): iterable
    {
        return $this->normalizeCatalog($this->fetchRawCatalog()->catalog, $providerName);
    }

    /**
     * Downloads and decodes the full catalog.
     *
     * The materialization happens here (and not lazily in a generator) so that callers can
     * download everything up front and keep existing data intact when the request fails.
     * Throws on transport and HTTP errors; a 304 Not Modified for a submitted ETag is not
     * an error but yields a "not modified" result (see {@see LiteLlmCatalogFetch}).
     *
     * @param string|null $etag When set, the request is made conditional via
     *   If-None-Match, so an unchanged upstream answers with a tiny 304 response.
     */
    public function fetchRawCatalog(string|null $etag = null): LiteLlmCatalogFetch
    {
        $request = $this->http
            ->timeout(10)
            ->connectTimeout(2)
            ->withHeader('accept', 'application/json')
            ->retry(1, 1000);

        if ($etag !== null) {
            $request = $request->withHeader('If-None-Match', $etag);
        }

        $response = $request->get(self::API_URL);

        if ($etag !== null && $response->status() === 304) {
            return LiteLlmCatalogFetch::notModified();
        }

        $catalog = $response->throw()->json() ?? [];

        return LiteLlmCatalogFetch::fetched($catalog, $response->header('ETag') ?: null);
    }

    /**
     * Normalizes a downloaded catalog into model records. Pure — performs no HTTP requests.
     *
     * Known non-model entries are skipped silently; unexpected entries without a valid
     * `litellm_provider` are skipped with a debug log so format changes remain visible.
     *
     * @param array<string, mixed> $catalog The decoded catalog, keyed by model ID.
     * @param string|null $providerName When set, only records for this LiteLLM provider are yielded.
     * @return iterable<int, array<string, mixed>> Normalized records with `id` and `provider`.
     */
    public function normalizeCatalog(array $catalog, string|null $providerName = null): iterable
    {
        foreach ($catalog as $modelId => $row) {
            if (in_array($modelId, self::NON_MODEL_KEYS, true)) {
                continue;
            }

            $provider = is_array($row) ? ($row['litellm_provider'] ?? null) : null;
            if (!is_string($provider) || $provider === '') {
                $this->logger->debug('Skipping unexpected non-model entry in LiteLLM catalog', [
                    'key' => $modelId,
                    'type' => get_debug_type($row),
                ]);
                continue;
            }

            if ($providerName !== null && $provider !== $providerName) {
                continue;
            }

            unset($row['litellm_provider']);
            yield [
                'id' => (string)$modelId,
                'provider' => $provider,
                ...$row,
            ];
        }
    }
}
