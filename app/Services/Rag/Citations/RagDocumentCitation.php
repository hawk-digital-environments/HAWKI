<?php

declare(strict_types=1);

namespace App\Services\Rag\Citations;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Laravel\Ai\Responses\Data\Citation;

/**
 * A knowledge-base document source an AI response was built from, shaped as
 * a gateway citation so it flows through the same channels as provider
 * citations: as a `Citation` stream event during streaming and inside
 * `TextResponse::$meta->citations` for synchronous responses.
 *
 * Deliberately extends the abstract {@see Citation} base and NOT
 * {@see \Laravel\Ai\Responses\Data\UrlCitation}: the URL cleaner only
 * processes UrlCitation instances, so these citations pass through
 * untouched — their URLs point at HAWKI's own storage proxy (or are empty
 * for documents without a local attachment, cited by name only).
 */
final class RagDocumentCitation extends Citation implements Arrayable, JsonSerializable
{
    /**
     * @param list<array{int, int}> $ranges
     */
    public function __construct(
        public readonly string $url,
        string $title,
        public readonly array $ranges = [],
        public readonly bool $document = true,
    ) {
        parent::__construct($title);
    }

    /**
     * @return array{url: string, title: string|null, ranges: list<array{int, int}>, document: bool}
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'title' => $this->title,
            'ranges' => $this->ranges,
            'document' => $this->document,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
