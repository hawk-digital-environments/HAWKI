<?php
declare(strict_types=1);


namespace App\Services\ExternalContent;


use Illuminate\Container\Attributes\Singleton;
use Laravel\Ai\Responses\Data\Citation;
use Laravel\Ai\Responses\Data\UrlCitation;

/**
 * Adapts {@see UrlCleaner} to work with AI response {@see Citation} objects.
 *
 * Only {@see UrlCitation} instances carry a resolvable URL; all other Citation subtypes are
 * passed through unchanged. When cleaning a batch, only the UrlCitation URLs are forwarded
 * to {@see UrlCleaner::cleanMany()} so they are resolved concurrently.
 *
 * Used in the streaming response pipeline to ensure that AI-generated citation URLs
 * point to the actual content rather than intermediate redirect chains with tracking parameters.
 *
 * Usage:
 * ```php
 * // Called after a streaming AI response completes:
 * $cleaned = $citationUrlCleaner->cleanMany($response->meta->citations->all());
 * foreach ($cleaned as $citation) {
 *     // citation URLs are now redirect-resolved and tracking-param-free
 * }
 * ```
 *
 * @see UrlCleaner the underlying URL resolution and sanitisation logic.
 */
#[Singleton]
readonly class CitationUrlCleaner
{
    public function __construct(private UrlCleaner $urlCleaner)
    {
    }

    /**
     * Clean a single citation.
     *
     * If the citation is a {@see UrlCitation}, its URL is resolved through the redirect chain
     * and stripped of tracking parameters. The URL is mutated directly on the passed object.
     * Any other Citation subtype is returned unchanged.
     */
    public function clean(Citation $citation): Citation
    {
        if ($citation instanceof UrlCitation) {
            $citation->url = $this->urlCleaner->clean($citation->url);
        }

        return $citation;
    }

    /**
     * Resolve all UrlCitation URLs concurrently and return the full citation list.
     *
     * Only {@see UrlCitation} instances are cleaned; other Citation subtypes pass through
     * unchanged. Cleaned citations are returned as clones so the originals are not mutated.
     *
     * @param Citation[] $citations
     * @return Citation[]
     */
    public function cleanMany(array $citations): array
    {
        $urlIndices = [];
        $urlsToResolve = [];

        foreach ($citations as $key => $citation) {
            if ($citation instanceof UrlCitation) {
                $urlIndices[] = $key;
                $urlsToResolve[] = $citation->url;
            }
        }

        if (empty($urlsToResolve)) {
            return $citations;
        }

        $resolved = $this->urlCleaner->cleanMany($urlsToResolve);

        foreach ($urlIndices as $i => $citationKey) {
            $originalCitation = $citations[$citationKey];
            if (!$originalCitation instanceof UrlCitation) {
                continue;
            }
            $clone = clone $originalCitation;
            $clone->url = $resolved[$i];
            $citations[$citationKey] = $clone;
        }

        return $citations;
    }
}
