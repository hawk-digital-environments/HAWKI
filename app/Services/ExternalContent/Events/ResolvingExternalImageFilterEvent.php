<?php
declare(strict_types=1);

namespace App\Services\ExternalContent\Events;

use App\Events\Traits\DispatchableFilter;
use App\Services\ExternalContent\Values\ResolvedExternalImage;

/**
 * Filter event dispatched before fetching an external image for a URL.
 *
 * A listener may call {@see setResolved()} to supply a pre-resolved image and
 * short-circuit the actual HTTP fetch. When a resolved value is present the
 * proxy skips the request and returns that value directly from the cache callback.
 *
 * Use this event to:
 * - Serve a locally cached or stored image without making an outbound HTTP request.
 * - Block fetching of specific URLs by providing a fallback image instead.
 * - Inject a placeholder or branded image for known domains.
 *
 * Read-only: {@see getUrl()}
 * Writable:  {@see getResolved()} / {@see setResolved()}
 */
class ResolvingExternalImageFilterEvent
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

    /** The image URL that is about to be fetched. */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Returns the pre-resolved image, or null if no listener has provided one.
     * A non-null return value causes the normal HTTP fetch to be skipped.
     */
    public function getResolved(): ResolvedExternalImage|null
    {
        return $this->resolved;
    }

    /**
     * Provide a pre-resolved image to skip the HTTP fetch.
     * The supplied image is stored in the proxy cache and returned to the caller.
     */
    public function setResolved(ResolvedExternalImage $resolved): void
    {
        $this->resolved = $resolved;
    }
}
