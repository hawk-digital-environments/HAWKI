<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm;


use Illuminate\Http\Client\Factory;

/**
 * HTTP client for the LiteLLM public model catalog API.
 *
 * Paginates through the catalog endpoint, optionally filtering by provider name.
 * Requests use aggressive timeouts (2 s connect, 2 s transfer) with a single retry,
 * because this is enrichment-only data and the caller falls back to static files on failure.
 *
 * @see StaticLiteLlmDataUpdater which uses this client to regenerate the static fallback files.
 */
readonly class LiteLlmApiClient
{
    private const string API_URL = 'https://api.litellm.ai/model_catalog';

    public function __construct(readonly private Factory $http)
    {
    }

    /**
     * Paginates through the LiteLLM model catalog and yields each raw model record as an array.
     *
     * @param string|null $providerName When set, only records for this LiteLLM provider are returned.
     */
    public function fetchData(string|null $providerName = null): iterable
    {
        $page = 1;
        $hasMore = true;
        while ($hasMore) {
            $url = $this->buildUrl($providerName, $page);
            $response = $this->makeApiRequest($url);
            yield from $response['data'] ?? [];

            $hasMore = $response['has_more'] ?? false;
            $page++;
        }
    }

    /** Executes a single GET request and returns the decoded JSON body. Throws on HTTP errors. */
    private function makeApiRequest(string $url): array
    {
        return $this->http
            ->timeout(2)
            ->connectTimeout(2)
            ->withHeader('accept', 'application/json')
            ->retry(1, 1000)
            ->get($url)
            ->throw()
            ->json();
    }

    /** Builds the catalog URL with provider filter and pagination; page size is clamped to 1–500. */
    private function buildUrl(
        string|null $providerName,
        int         $page = 1,
        int         $pageSize = 500
    ): string
    {
        $pageSize = max(1, min($pageSize, 500)); // Ensure page size is between 1 and 500
        $args = array_filter([
            'provider' => $providerName,
            'page' => $page,
            'page_size' => $pageSize,
        ]);
        $queryString = http_build_query($args);
        return self::API_URL . '?' . $queryString;
    }
}
