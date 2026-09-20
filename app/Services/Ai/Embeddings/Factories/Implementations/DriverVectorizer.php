<?php

declare(strict_types=1);

namespace App\Services\Ai\Embeddings\Factories\Implementations;

use App\Models\Ai\AiModel;
use App\Services\Ai\Embeddings\Contracts\VectorizerInterface;
use App\Services\Ai\Embeddings\Values\EmbeddingRequest;
use Laravel\Ai\Contracts\Providers\EmbeddingProvider;
use Laravel\Ai\Responses\EmbeddingsResponse;

/**
 * The vectorizer produced by {@see DefaultVectorizerFactory}: drives one resolved
 * provider driver's embeddings gateway directly.
 *
 * Goes through HAWKI's per-provider driver config (credentials, base URLs) because the
 * driver was created by the adapter registry — unlike the fluent
 * `Laravel\Ai\Embeddings::for()` API, whose provider-by-name resolution would bypass it.
 */
readonly class DriverVectorizer implements VectorizerInterface
{
    public function __construct(
        private EmbeddingProvider $driver,
        private AiModel $model,
    ) {
    }

    public function model(): AiModel
    {
        return $this->model;
    }

    public function vectorize(EmbeddingRequest $request): EmbeddingsResponse
    {
        return $this->driver->embeddings(
            inputs: $request->input,
            dimensions: $request->dimensions,
            model: $this->model->model_id,
        );
    }
}
