<?php

declare(strict_types=1);

namespace App\Services\WebSearch\Values;

/**
 * Normalized result set of a web search. `answer` is an optional provider-generated
 * summary (e.g. Tavily's `include_answer`); null when the provider offers none.
 */
readonly class WebSearchResponse implements \JsonSerializable
{
    /**
     * @param list<WebSearchResult> $results
     */
    public function __construct(
        public string $query,
        public array $results,
        public string|null $answer = null,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'query' => $this->query,
            'answer' => $this->answer,
            'results' => $this->results,
        ];
    }
}
