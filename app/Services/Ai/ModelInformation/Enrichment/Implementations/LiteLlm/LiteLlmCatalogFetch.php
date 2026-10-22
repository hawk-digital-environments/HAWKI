<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm;


/**
 * The outcome of a LiteLLM catalog download via {@see LiteLlmApiClient::fetchRawCatalog()}.
 *
 * When an ETag was submitted and the upstream catalog did not change since, the outcome is
 * marked as "not modified" and carries no catalog data — the previously downloaded catalog
 * remains the current one.
 */
readonly class LiteLlmCatalogFetch
{
    /**
     * @param array<string, mixed> $catalog The decoded catalog, keyed by model ID. Always
     *   empty when {@see $notModified} is true.
     * @param string|null $etag The ETag of the downloaded representation, when the server
     *   provided one. Null when the response was a 304 Not Modified.
     * @param bool $notModified True when the server answered the submitted ETag with
     *   304 Not Modified, i.e. the previously downloaded catalog is still current.
     */
    public function __construct(
        public readonly array     $catalog,
        public readonly string|null $etag,
        public readonly bool      $notModified,
    )
    {
    }

    /** @param array<string, mixed> $catalog */
    public static function fetched(array $catalog, string|null $etag): self
    {
        return new self($catalog, $etag, false);
    }

    public static function notModified(): self
    {
        return new self([], null, true);
    }
}
