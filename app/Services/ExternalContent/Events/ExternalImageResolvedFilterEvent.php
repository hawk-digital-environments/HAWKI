<?php
declare(strict_types=1);

namespace App\Services\ExternalContent\Events;

use App\Events\Traits\DispatchableFilter;
use App\Services\ExternalContent\Values\ResolvedExternalImage;

/**
 * Filter event dispatched after an external image has been fetched (or a fallback generated),
 * before the result is written to the proxy cache and returned to the caller.
 *
 * Listeners may inspect the resolved image and replace it via {@see setImage()} before
 * it is cached.
 *
 * Use this event to:
 * - Post-process the image (e.g. resize, watermark, or convert format) before caching.
 * - Replace a generated fallback gradient with a domain-specific placeholder.
 * - Log or audit which images are proxied through the application.
 *
 * Read-only: {@see getUrl()}
 * Writable:  {@see getImage()} / {@see setImage()}
 */
class ExternalImageResolvedFilterEvent
{
    use DispatchableFilter;

    private ResolvedExternalImage $image;

    public function __construct(
        private readonly string $url,
        ResolvedExternalImage   $image,
    )
    {
        $this->image = $image;
    }

    /** The URL from which the image was (or would have been) fetched. */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Returns the currently resolved image.
     * This may already have been modified by an earlier listener in the same dispatch.
     */
    public function getImage(): ResolvedExternalImage
    {
        return $this->image;
    }

    /**
     * Replace the resolved image that will be cached and returned to the caller.
     */
    public function setImage(ResolvedExternalImage $image): void
    {
        $this->image = $image;
    }
}
