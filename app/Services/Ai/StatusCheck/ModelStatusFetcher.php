<?php
declare(strict_types=1);


namespace App\Services\Ai\StatusCheck;


use NeuronAI\HttpClient\HttpClientInterface;
use NeuronAI\HttpClient\HttpRequest;
use NeuronAI\HttpClient\HttpResponse;

/**
 * Thin HTTP wrapper used during model status checks.
 *
 * An instance is created by {@see ModelStatusUpdater} (pre-configured with the
 * provider's authenticated HTTP client and an optional URI override) and passed
 * into every {@see \App\Services\Ai\ProviderAdapters\Contracts\ProviderAdapterInterface::checkModelStatus()}
 * implementation. Adapters call {@see self::get()} or {@see self::getExtract()} to
 * query the provider's model-list endpoint and feed the results into the
 * {@see AiModelOnlineStatusCollection}.
 *
 * The optional `$modelStatusUri` constructor argument lets an operator redirect
 * all requests to a fixed URL regardless of what the adapter passes as `$uri`.
 * This is useful when the status endpoint differs from the inference API base URL
 * (e.g. a separate health-check host).
 *
 * Usage example inside a provider adapter:
 *
 * ```php
 * public function checkModelStatus(
 *     AiModelOnlineStatusCollection $statusCollection,
 *     ModelStatusFetcher $fetcher,
 *     AiProvider $provider,
 * ): void {
 *     // Extract the list of model IDs from the provider's /models response and
 *     // mark each one that is known to this HAWKI instance as online.
 *     foreach ($fetcher->getExtract('/models', 'data.*.id') as $modelId) {
 *         $statusCollection->setOnlineById($modelId);
 *     }
 * }
 * ```
 */
readonly class ModelStatusFetcher
{
    public function __construct(
        private HttpClientInterface $client,
        /**
         * When set, every call to {@see self::get()} uses this URI instead of
         * the one provided by the adapter. Allows operator-level overrides of
         * the status endpoint without changing adapter code.
         */
        private string|null         $modelStatusUrl = null,
    )
    {
    }

    /**
     * Returns the underlying HTTP client.
     *
     * Adapters that need to make lower-level requests (e.g. with custom headers)
     * can retrieve the pre-configured client via this accessor.
     */
    public function getClient(): HttpClientInterface
    {
        return $this->client;
    }

    /**
     * Performs a GET request and returns the raw response.
     *
     * If a `$modelStatusUrl` override was provided at construction time, it
     * takes precedence over the `$url` argument passed here.
     *
     * @throws \RuntimeException when the response status code is not 2xx.
     */
    public function get(string $url): HttpResponse
    {
        $url = $this->modelStatusUrl ?? $url;
        $response = $this->client->request(HttpRequest::get($url));
        if (!$response->isSuccessful()) {
            throw new \RuntimeException(sprintf('Failed to fetch model status from %s: %s', $url, $response->body));
        }
        return $response;
    }

    /**
     * Performs a GET request, decodes the JSON body, and extracts a nested
     * value using a dot-notation path (resolved via Laravel's {@see data_get()}).
     *
     * Adapters use this to pull a flat list of model identifiers out of a
     * provider's model-listing response without having to parse the full JSON
     * structure themselves.
     *
     * @param string $extractPath Dot-notation path into the decoded JSON body,
     *                            e.g. `"data.*.id"` or `"models.*.baseModelId"`.
     *
     * @throws \RuntimeException when the HTTP request fails or the value at
     *                           `$extractPath` is not an array.
     */
    public function getExtract(string $uri, string $extractPath): array
    {
        $data = $this->get($uri)->json();

        $extractValue = data_get($data, $extractPath);
        if (!is_array($extractValue)) {
            throw new \RuntimeException(sprintf('Extracted value at path "%s" is not an array as expected: %s', $extractPath, var_export($extractValue, true)));
        }

        return $extractValue;
    }
}
