<?php
declare(strict_types=1);

namespace App\Services\ExternalContent\Events;

use App\Events\Traits\DispatchableFilter;
use App\Services\ExternalContent\Values\ResolvedExternalImage;

/**
 * Filter event dispatched before fetching a favicon for a URL.
 *
 * A listener may call {@see setResolved()} to supply a pre-resolved image and
 * short-circuit the actual HTTP fetch entirely. When a resolved value is present
 * the favicon service skips contacting Google's favicon endpoint and returns that
 * value directly from the cache callback.
 *
 * Use this event to:
 * - Serve a custom favicon for a specific domain without making an HTTP request.
 * - Return a cached or stored favicon that bypasses the default resolution path.
 * - Skip fetching for domains that are known to return no usable favicon.
 *
 * Read-only: {@see getUrl()}
 * Writable:  {@see getResolved()} / {@see setResolved()}
 */
class ResolvingFaviconFilterEvent
{
    use DispatchableFilter;

    private ResolvedExternalImage|null $resolved;

    public function __construct(
        private readonly string    $url,
        ResolvedExternalImage|null $resolved = null,
    )
    {
        $this->resolved = $resolved;
    }

    /** The URL whose favicon is about to be fetched. */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Returns the pre-resolved favicon image, or null if no listener has provided one.
     * A non-null return value causes the normal HTTP fetch to be skipped.
     */
    public function getResolved(): ResolvedExternalImage|null
    {
        return $this->resolved;
    }

    /**
     * Provide a pre-resolved favicon image to skip the HTTP fetch.
     * The supplied image is stored in the favicon cache and returned to the caller.
     */
    public function setResolved(ResolvedExternalImage $resolved): void
    {
        $this->resolved = $resolved;
    }
}
