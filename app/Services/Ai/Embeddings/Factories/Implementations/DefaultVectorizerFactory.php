<?php

declare(strict_types=1);

namespace App\Services\Ai\Embeddings\Factories\Implementations;

use App\Services\Ai\Embeddings\Contracts\VectorizerInterface;
use App\Services\Ai\Embeddings\Exceptions\EmbeddingNotSupportedException;
use App\Services\Ai\Embeddings\Factories\Contracts\VectorizerFactoryInterface;
use App\Services\Ai\Embeddings\Values\EmbeddingRequest;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use Illuminate\Container\Attributes\Singleton;
use Laravel\Ai\Contracts\Providers\EmbeddingProvider;

/**
 * Default vectorizer factory: resolves the requested model from the catalogue, wires
 * its provider driver, and returns a {@see DriverVectorizer} when that driver can
 * produce embeddings.
 *
 * Model resolution mirrors {@see \App\Services\Ai\Chat\Factories\Implementations\ChatAgentFactory}:
 * an explicit model id is required (the formatter enforces this) and an unknown id
 * bubbles {@see \App\Services\Ai\Exceptions\ModelIdNotAvailableException} for the
 * controller to map onto a 404. Driver capability — not the catalogue's `model_type`
 * label — decides embeddings support.
 */
#[Singleton()]
readonly class DefaultVectorizerFactory implements VectorizerFactoryInterface
{
    public function __construct(
        private AiModelRepository $modelRepository,
        private AiProviderProxyResolver $providerProxyResolver,
    ) {
    }

    public function createVectorizer(EmbeddingRequest $request): ?VectorizerInterface
    {
        $model = $this->modelRepository->findOneOrFail($request->model);
        $proxy = $this->providerProxyResolver->resolveForModel($model);

        $driver = $proxy->driver;

        if (!$driver instanceof EmbeddingProvider) {
            throw EmbeddingNotSupportedException::forModel($model);
        }

        return new DriverVectorizer(
            driver: $driver,
            model: $model,
        );
    }
}
