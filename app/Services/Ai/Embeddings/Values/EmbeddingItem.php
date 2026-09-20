<?php

declare(strict_types=1);

namespace App\Services\Ai\Embeddings\Values;

/**
 * A single embedding vector, aligned to the request input by {@see $index}.
 *
 * @api
 */
readonly class EmbeddingItem
{
    /**
     * @param array<int, float> $embedding
     */
    public function __construct(
        public array $embedding,
        public int $index,
    ) {
    }
}
