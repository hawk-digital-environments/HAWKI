<?php

declare(strict_types=1);

namespace App\Services\Ai\Embeddings\Values;

/**
 * Token usage of a single embeddings invocation.
 *
 * Embeddings consume prompt tokens only; there is no completion side.
 *
 * @api
 */
readonly class EmbeddingUsageInfo
{
    public function __construct(
        public int $promptTokens = 0,
        public int $totalTokens = 0,
    ) {
    }
}
