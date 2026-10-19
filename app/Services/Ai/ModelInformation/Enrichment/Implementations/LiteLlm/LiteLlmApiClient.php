<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm;


use Illuminate\Http\Client\Factory;

/**
 * HTTP client for the LiteLLM model catalog (`model_prices_and_context_window.json`).
 *
 * The catalog is a single JSON object keyed by model ID; each entry names its provider in
 * `litellm_provider`. Non-model entries (e.g. `sample_spec`) carry no provider and are skipped.
 * Every yielded record is normalized to carry `id` (the object key) and `provider`
 * (the `litellm_provider` value), which is the shape the data stores expect.
 *
 * The whole catalog (~3 MB) is downloaded per call, so the transfer timeout is more lenient
 * than the connect timeout. Failures are thrown because the caller falls back to static files.
 *
 * @see StaticLiteLlmDataUpdater which uses this client to regenerate the static fallback files.
 */
readonly class LiteLlmApiClient
{
    private const string API_URL = 'https://raw.githubusercontent.com/BerriAI/litellm/refs/heads/main/model_prices_and_context_window.json';

    public function __construct(readonly private Factory $http)
    {
    }

    /**
     * Downloads the LiteLLM model catalog and yields each model record as a normalized array.
     *
     * @param string|null $providerName When set, only records for this LiteLLM provider are returned.
     */
    public function fetchData(string|null $providerName = null): iterable
    {
        foreach ($this->makeApiRequest() as $modelId => $row) {
            if (!is_array($row) || empty($row['litellm_provider']) || !is_string($row['litellm_provider'])) {
                continue;
            }

            if ($providerName !== null && $row['litellm_provider'] !== $providerName) {
                continue;
            }

            yield [
                'id' => (string)$modelId,
                'provider' => $row['litellm_provider'],
                ...$row,
            ];
        }
    }

    /** Executes the GET request and returns the decoded JSON body. Throws on HTTP errors. */
    private function makeApiRequest(): array
    {
        return $this->http
            ->timeout(10)
            ->connectTimeout(2)
            ->withHeader('accept', 'application/json')
            ->retry(1, 1000)
            ->get(self::API_URL)
            ->throw()
            ->json() ?? [];
    }
}
