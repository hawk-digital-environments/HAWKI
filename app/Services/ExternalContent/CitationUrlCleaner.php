<?php
declare(strict_types=1);


namespace App\Services\ExternalContent;


use Illuminate\Container\Attributes\Singleton;
use Laravel\Ai\Responses\Data\Citation;
use Laravel\Ai\Responses\Data\UrlCitation;

#[Singleton]
readonly class CitationUrlCleaner
{
    public function __construct(private UrlCleaner $urlCleaner)
    {
    }

    public function clean(Citation $citation): Citation
    {
        if ($citation instanceof UrlCitation) {
            $citation->url = $this->urlCleaner->clean($citation->url);
        }

        return $citation;
    }

    /**
     * Resolves all UrlCitation URLs concurrently.
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
            $clone = clone $citations[$citationKey];
            $clone->url = $resolved[$i];
            $citations[$citationKey] = $clone;
        }

        return $citations;
    }
}
