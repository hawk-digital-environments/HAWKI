<?php

declare(strict_types=1);

namespace App\Services\WebSearch\Values;

/**
 * A single, provider-independent search hit.
 */
readonly class WebSearchResult implements \JsonSerializable
{
    public function __construct(
        public string $title,
        public string $url,
        public string $content,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'title' => $this->title,
            'url' => $this->url,
            'content' => $this->content,
        ];
    }
}
