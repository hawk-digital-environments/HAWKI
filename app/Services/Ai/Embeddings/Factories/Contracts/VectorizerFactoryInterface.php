<?php

declare(strict_types=1);

namespace App\Services\Ai\Embeddings\Factories\Contracts;

use App\Services\Ai\Embeddings\Contracts\VectorizerInterface;
use App\Services\Ai\Embeddings\Values\EmbeddingRequest;

/**
 * A factory claims an {@see EmbeddingRequest} and produces the
 * {@see VectorizerInterface} capable of executing it.
 *
 * The embeddings counterpart of
 * {@see \App\Services\Ai\Chat\Factories\Contracts\ChatAgentFactoryInterface}. Factories
 * return null when they cannot handle a request; the first non-null vectorizer wins.
 *
 * @api
 */
interface VectorizerFactoryInterface
{
    public function createVectorizer(EmbeddingRequest $request): ?VectorizerInterface;
}
