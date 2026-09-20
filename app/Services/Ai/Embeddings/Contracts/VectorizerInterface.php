<?php

declare(strict_types=1);

namespace App\Services\Ai\Embeddings\Contracts;

use App\Models\Ai\AiModel;
use App\Services\Ai\Embeddings\Values\EmbeddingRequest;
use Laravel\Ai\Responses\EmbeddingsResponse;

/**
 * A vectorizer executes an embeddings request against one provider driver.
 *
 * The embeddings counterpart of {@see \App\Services\Ai\Agents\Contracts\AgentInterface}:
 * it speaks the vendor SDK types ({@see EmbeddingsResponse}) and exposes the resolved
 * catalogue model ({@see model()}) so callers can record usage without a second lookup
 * — the parallel of {@see \App\Services\Ai\Agents\Values\AgentRequestContext::$model} on
 * the chat side.
 *
 * @api
 */
interface VectorizerInterface
{
    /**
     * The catalogue model this vectorizer executes against.
     */
    public function model(): AiModel;

    /**
     * @throws \Laravel\Ai\Exceptions\AiException on provider failure
     */
    public function vectorize(EmbeddingRequest $request): EmbeddingsResponse;
}
