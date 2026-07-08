<?php
declare(strict_types=1);

namespace App\Services\ExternalContent\Events;

use App\Events\Traits\DispatchableFilter;
use App\Services\ExternalContent\Values\WebsiteMetadata;

/**
 * Filter event dispatched after website metadata has been resolved for a URL (either parsed
 * from the HTTP response or constructed as a fallback), before the result is cached.
 *
 * Listeners may inspect the resolved metadata and replace it via {@see setData()} before
 * it is written to the cache and returned to the caller.
 *
 * Use this event to:
 * - Enrich metadata with data from an external source (e.g. a custom title database).
 * - Sanitise or normalise titles and descriptions before they are stored.
 * - Override fallback metadata that was generated because the real fetch failed.
 *
 * Read-only: {@see getUrl()}
 * Writable:  {@see getData()} / {@see setData()}
 */
class WebsiteMetadataResolvedFilterEvent
{
    use DispatchableFilter;

    private WebsiteMetadata $data;

    public function __construct(
        private readonly string $url,
        WebsiteMetadata         $data,
    )
    {
        $this->data = $data;
    }

    /** The URL whose metadata was resolved. */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Returns the currently resolved website metadata.
     * This may already have been modified by an earlier listener in the same dispatch.
     */
    public function getData(): WebsiteMetadata
    {
        return $this->data;
    }

    /**
     * Replace the resolved metadata that will be cached and returned to the caller.
     */
    public function setData(WebsiteMetadata $data): void
    {
        $this->data = $data;
    }
}
