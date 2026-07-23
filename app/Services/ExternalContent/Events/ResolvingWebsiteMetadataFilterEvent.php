<?php
declare(strict_types=1);

namespace App\Services\ExternalContent\Events;

use App\Events\Traits\DispatchableFilter;
use App\Services\ExternalContent\Values\WebsiteMetadata;

/**
 * Filter event dispatched before fetching and parsing website metadata for a URL.
 *
 * A listener may call {@see setResolved()} to supply pre-built metadata and
 * short-circuit the actual HTTP fetch. When a resolved value is present the loader
 * skips the request and returns that value directly from the cache callback.
 *
 * Use this event to:
 * - Return hardcoded or stored metadata for specific URLs without making an HTTP request.
 * - Inject metadata for URLs that are not publicly reachable (e.g. intranet links).
 * - Override metadata fetching during testing or in sandboxed environments.
 *
 * Read-only: {@see getUrl()}
 * Writable:  {@see getResolved()} / {@see setResolved()}
 */
class ResolvingWebsiteMetadataFilterEvent
{
    use DispatchableFilter;

    private WebsiteMetadata|null $resolved;

    public function __construct(
        private readonly string $url,
        WebsiteMetadata|null    $resolved = null,
    )
    {
        $this->resolved = $resolved;
    }

    /** The URL whose metadata is about to be fetched. */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Returns the pre-resolved metadata, or null if no listener has provided any.
     * A non-null return value causes the normal HTTP fetch to be skipped.
     */
    public function getResolved(): WebsiteMetadata|null
    {
        return $this->resolved;
    }

    /**
     * Provide pre-built metadata to skip the HTTP fetch.
     * The supplied metadata is written to cache and returned to the caller.
     */
    public function setResolved(WebsiteMetadata $resolved): void
    {
        $this->resolved = $resolved;
    }
}
