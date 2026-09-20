<?php

declare(strict_types=1);

namespace App\Services\Ai\Embeddings\Values;

/**
 * Embeddings response IR — the domain counterpart of {@see \App\Services\Ai\Chat\Values\AiResponse}.
 *
 * {@see $data} is index-ordered to match the originating {@see EmbeddingRequest::$input};
 * that ordering guarantee is the core of every embeddings wire format.
 *
 * @api
 */
readonly class EmbeddingResponse
{
    /**
     * @param list<EmbeddingItem> $data index-aligned to the request input
     */
    public function __construct(
        public string $model,
        public array $data,
        public EmbeddingUsageInfo $usage,
    ) {
    }
}
