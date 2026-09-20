<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Embeddings\EmbeddingsEndpointTestFixtures;

use App\Models\Ai\AiModel;
use App\Services\Ai\Embeddings\Contracts\VectorizerInterface;
use App\Services\Ai\Embeddings\Values\EmbeddingRequest;
use Laravel\Ai\Responses\EmbeddingsResponse;

/**
 * Deterministic vectorizer for endpoint tests: returns a fixed vendor response and
 * records every received {@see EmbeddingRequest} for input-normalisation assertions.
 */
class FakeVectorizer implements VectorizerInterface
{
    /**
     * @param \ArrayObject<int, EmbeddingRequest> $received
     */
    public function __construct(
        private readonly EmbeddingsResponse $response,
        private readonly AiModel $model,
        private readonly \ArrayObject $received = new \ArrayObject(),
    ) {
    }

    public function model(): AiModel
    {
        return $this->model;
    }

    public function vectorize(EmbeddingRequest $request): EmbeddingsResponse
    {
        $this->received[] = $request;

        return $this->response;
    }

    /**
     * @return list<EmbeddingRequest>
     */
    public function receivedRequests(): array
    {
        return $this->received->getArrayCopy();
    }
}
