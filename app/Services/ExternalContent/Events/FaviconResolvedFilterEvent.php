<?php
declare(strict_types=1);

namespace App\Services\ExternalContent\Events;

use App\Events\Traits\DispatchableFilter;
use App\Services\ExternalContent\Values\ResolvedExternalImage;

/**
 * Filter event dispatched after a favicon has been resolved for a URL, before it is cached.
 *
 * Listeners may inspect the resolved icon and replace it via {@see setIcon()} before the
 * result is written to the cache and returned to the caller.
 *
 * Use this event to:
 * - Replace a low-quality or fallback icon with a better one from a local source.
 * - Modify the MIME type or content of the resolved favicon image.
 * - Log or audit which favicon was resolved for a given domain.
 *
 * Read-only: {@see getUrl()}
 * Writable:  {@see getIcon()} / {@see setIcon()}
 */
class FaviconResolvedFilterEvent
{
    use DispatchableFilter;

    private ResolvedExternalImage $icon;

    public function __construct(
        private readonly string $url,
        ResolvedExternalImage   $icon,
    )
    {
        $this->icon = $icon;
    }

    /** The URL whose favicon was resolved. */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Returns the currently resolved favicon.
     * This may already have been modified by an earlier listener in the same dispatch.
     */
    public function getIcon(): ResolvedExternalImage
    {
        return $this->icon;
    }

    /**
     * Replace the resolved favicon that will be cached and returned to the caller.
     */
    public function setIcon(ResolvedExternalImage $icon): void
    {
        $this->icon = $icon;
    }
}
