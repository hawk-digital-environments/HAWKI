<?php
declare(strict_types=1);


namespace App\Services\ExternalContent\Values;


/**
 * Carries the raw binary content and MIME type of a resolved external image.
 *
 * Produced by {@see FavIconProxy} and {@see ExternalImageProxy} and returned directly to
 * the browser via the link preview controller. When {@see $isFallback} is true the content
 * was generated locally (a gradient PNG or SVG placeholder) rather than fetched from the
 * external URL.
 */
readonly class ResolvedExternalImage
{
    public function __construct(
        public string $content,
        public string $mimeType,
        public bool   $isFallback = false
    )
    {
    }
}
